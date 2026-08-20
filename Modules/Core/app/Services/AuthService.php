<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\UserCapabilityResolver;
use Modules\Core\Data\AuthPayload;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Notifications\NewDeviceLoginNotification;
use Modules\Core\Notifications\PasswordChangedNotification;
use Modules\Core\Notifications\PasswordResetCompletedNotification;
use Modules\Core\Notifications\RegistrationCompletedNotification;
use Modules\Core\Notifications\SuspiciousLoginAttemptNotification;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserProfileRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Support\PhoneNumber;

final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserProfileRepositoryInterface $profiles,
        private readonly VerificationCodeService $verificationCodes,
        private readonly PasswordHistoryService $passwordHistory,
        private readonly MfaService $mfa,
        private readonly ProviderRepositoryInterface $providers,
        private readonly UserCapabilityResolver $capabilities,
        private readonly DeviceRecognitionService $devices,
    ) {}

    /**
     * @param  array{name: string, email: ?string, mobile: ?string, password: string, account_types?: array<int, string>}  $data
     */
    public function register(array $data): AuthPayload
    {
        return DB::transaction(function () use ($data) {
            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'mobile' => PhoneNumber::normalize($data['mobile'] ?? null),
                'password' => $data['password'],
                'terms_accepted_at' => now(),
                'status' => UserStatus::Active,
                'account_types' => $data['account_types'] ?? null,
            ]);

            // New profiles default to the platform's default country/currency (Saudi Arabia/SAR)
            // rather than starting blank — the user can change either via PUT /profile later.
            $this->profiles->createForUser($user, [
                'full_name' => $data['name'],
                'country_id' => Country::default()?->id,
                'preferred_currency_id' => Currency::default()?->id,
            ]);

            // Registration accepts mobile or email (FR-AUTH-001) — verify whichever one was
            // actually provided, preferring email if both were given.
            $this->verificationCodes->issue(
                $user,
                filled($data['email'] ?? null) ? VerificationCodeType::Email : VerificationCodeType::Mobile,
                VerificationCodePurpose::AccountVerification,
            );

            $this->passwordHistory->record($user, $user->password);

            $token = $user->createToken('api-token');

            $user->notify(new RegistrationCompletedNotification);

            return $this->buildAuthPayload($user, $token->plainTextToken);
        });
    }

    /**
     * @return array{mfa_required: false, payload: AuthPayload}|array{mfa_required: true, challenge_token: string, expires_in: int}
     *
     * @throws ValidationException Invalid credentials, or the account can't sign in.
     */
    public function login(string $login, string $password, ?string $deviceName, ?string $ip, ?string $userAgent = null): array
    {
        $user = $this->users->findByLoginIdentifier($login);

        // Same generic error for "no such account" and "wrong password" — a distinct message for
        // the first case would let an attacker enumerate registered emails/mobiles.
        if (! $user || ! Hash::check($password, $user->password)) {
            if ($user !== null) {
                // Only counted/notified for a REAL account — never for an identifier that matches
                // nothing, or this endpoint could be used to enumerate registered emails/mobiles
                // via which ones trigger a notification.
                $this->recordFailedLoginAttempt($user);
            }

            throw ValidationException::withMessages([
                'login' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->canAuthenticate()) {
            throw ValidationException::withMessages([
                'login' => ['This account cannot sign in. Contact support if you believe this is a mistake.'],
            ]);
        }

        if ($user->hasMfaEnabled()) {
            // No token issued, last_login_at/last_login_ip not yet updated — those only mean
            // "completed a login," and this one isn't complete until the challenge is answered.
            // See docs/decisions/0012-totp-mfa-with-two-step-login.md.
            return [
                'mfa_required' => true,
                'challenge_token' => $this->mfa->issueChallenge($user, $deviceName, $ip),
                'expires_in' => (int) config('core.mfa.challenge_ttl_seconds'),
            ];
        }

        return ['mfa_required' => false, 'payload' => $this->issueAuthenticatedSession($user, $deviceName, $ip, $userAgent)];
    }

    /**
     * Step 2 of MFA login — exchanges a challenge token + TOTP/recovery code for the same session
     * a direct (non-MFA) login would have issued. One generic failure for both "expired/unknown
     * token" and "wrong code," preserving login()'s existing no-enumeration posture.
     */
    public function consumeMfaChallenge(string $challengeToken, string $code, ?string $userAgent = null): ?AuthPayload
    {
        $result = $this->mfa->consumeChallenge($challengeToken, $code);

        if ($result === null) {
            return null;
        }

        /** @var User $user */
        $user = $this->users->findById($result['user_id']);

        return $this->issueAuthenticatedSession($user, $result['device_name'], $result['ip'], $userAgent);
    }

    /**
     * The one place a Sanctum token is actually minted for a login — shared by a direct login and
     * a completed MFA challenge (register() mints its own, since it never faces an MFA challenge).
     * Keeping this in one method is what lets device recognition attach in exactly one place
     * instead of being duplicated across every path that can end in an issued session.
     */
    private function issueAuthenticatedSession(User $user, ?string $deviceName, ?string $ip, ?string $userAgent): AuthPayload
    {
        $this->users->forceUpdate($user, ['last_login_at' => now(), 'last_login_ip' => $ip]);

        $token = $user->createToken($deviceName !== null && $deviceName !== '' ? $deviceName : 'api-token');

        if ($this->devices->recognize($user, $deviceName, $ip, $userAgent)) {
            $user->notify(new NewDeviceLoginNotification($deviceName, $ip, now()));
        }

        return $this->buildAuthPayload($user, $token->plainTextToken);
    }

    /**
     * Increments a short-lived Redis counter and notifies once per window after crossing the
     * threshold (a `:notified` flag stops a brute-force run from sending dozens of emails).
     */
    private function recordFailedLoginAttempt(User $user): void
    {
        $windowMinutes = (int) config('core.security.suspicious_login.window_minutes');
        $threshold = (int) config('core.security.suspicious_login.threshold');

        $countKey = "umrany:core:failed-logins:{$user->id}";
        $notifiedKey = "{$countKey}:notified";

        $attempts = Cache::has($countKey) ? Cache::increment($countKey) : tap(1, fn () => Cache::put($countKey, 1, now()->addMinutes($windowMinutes)));

        if ($attempts >= $threshold && ! Cache::has($notifiedKey)) {
            Cache::put($notifiedKey, true, now()->addMinutes($windowMinutes));
            $user->notify(new SuspiciousLoginAttemptNotification($attempts));
        }
    }

    private function buildAuthPayload(User $user, string $plainTextToken): AuthPayload
    {
        $capabilities = $this->capabilities->capabilitiesFor($user->id);
        $provider = $capabilities->isProvider ? $this->providers->findByUserId($user->id) : null;

        return new AuthPayload($user, $plainTextToken, $capabilities, $provider);
    }

    public function resendVerificationCode(User $user, VerificationCodeType $type): void
    {
        $this->verificationCodes->issue($user, $type, VerificationCodePurpose::AccountVerification);
    }

    public function verifyAccount(User $user, VerificationCodeType $type, string $plainCode): bool
    {
        if (! $this->verificationCodes->consume($user, $type, VerificationCodePurpose::AccountVerification, $plainCode)) {
            return false;
        }

        match ($type) {
            VerificationCodeType::Email => $this->users->forceUpdate($user, ['email_verified_at' => now()]),
            VerificationCodeType::Mobile => $this->users->forceUpdate($user, ['mobile_verified_at' => now()]),
        };

        return true;
    }

    public function requestPasswordReset(User $user, VerificationCodeType $type): void
    {
        $this->verificationCodes->issue($user, $type, VerificationCodePurpose::PasswordReset);
    }

    /**
     * @throws ValidationException The new password matches a previously used one.
     */
    public function resetPassword(User $user, VerificationCodeType $type, string $plainCode, string $newPassword): bool
    {
        if (! $this->verificationCodes->consume($user, $type, VerificationCodePurpose::PasswordReset, $plainCode)) {
            return false;
        }

        // Deliberately checked here, not as a rule on ResetPasswordRequest — FormRequest rules run
        // before the OTP above is consumed, so a reuse-check there would let an attacker with no
        // valid code learn "this account exists and this password was used before" from a
        // validation error alone. See Modules\Core\Rules\NotAPreviousPassword and
        // docs/decisions/0013-config-driven-password-policy-and-history.md.
        if ($this->passwordHistory->isReused($user, $newPassword)) {
            throw ValidationException::withMessages([
                'password' => ['This password has been used before. Choose a different password.'],
            ]);
        }

        $this->users->forceUpdate($user, ['password' => $newPassword]);
        $this->passwordHistory->record($user, $user->password);

        // A password reset invalidates every existing session token — a device that stayed logged
        // in with the old password should not silently keep working after a reset that was likely
        // triggered because the account was compromised.
        $user->tokens()->delete();

        $user->notify(new PasswordResetCompletedNotification);

        return true;
    }

    /**
     * The authenticated "I know my current password and want to set a new one" flow — distinct
     * from resetPassword (the unauthenticated, OTP-based recovery flow above). Both end with the
     * same email confirmation. Current-password correctness is already guaranteed by
     * ChangePasswordRequest's `current_password:sanctum` rule by the time this runs — not
     * re-checked here, so there's exactly one place that owns "was the current password right".
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $this->users->forceUpdate($user, ['password' => $newPassword]);
        $this->passwordHistory->record($user, $user->password);

        // Sanctum's own stub types currentAccessToken() as non-nullable (@return TToken, no null
        // union), but the underlying $accessToken property genuinely is nullable — it's only
        // populated when $user was resolved via Sanctum authentication. Every current caller of
        // changePassword() passes an authenticated $request->user(), so this is safe today, but
        // the nullsafe stays as defensive correctness against the real (not documented) type.
        // @phpstan-ignore nullsafe.neverNull
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $user->notify(new PasswordChangedNotification);
    }

    /**
     * Self-service account deletion — password-confirmed by DeleteAccountRequest's
     * `current_password:sanctum` rule before this runs. Revokes every token first (belt-and-
     * braces: PersonalAccessToken::tokenable() is a morphTo, so a trashed user's surviving token
     * already resolves to null and 401s on its own once the soft-delete below applies).
     */
    public function deleteAccount(User $user): void
    {
        $user->tokens()->delete();
        $this->users->softDelete($user);
    }
}
