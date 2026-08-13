<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Notifications\PasswordChangedNotification;
use Modules\Core\Repositories\Contracts\UserProfileRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;

final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserProfileRepositoryInterface $profiles,
        private readonly VerificationCodeService $verificationCodes,
    ) {}

    /**
     * @param  array{name: string, email: ?string, mobile: ?string, password: string}  $data
     * @return array{0: User, 1: NewAccessToken}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'password' => $data['password'],
                'terms_accepted_at' => now(),
                'status' => UserStatus::Active,
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

            $token = $user->createToken('api-token');

            return [$user, $token];
        });
    }

    /**
     * @return array{0: User, 1: NewAccessToken}
     */
    public function login(string $login, string $password, ?string $deviceName, ?string $ip): array
    {
        $user = $this->users->findByLoginIdentifier($login);

        // Same generic error for "no such account" and "wrong password" — a distinct message for
        // the first case would let an attacker enumerate registered emails/mobiles.
        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->canAuthenticate()) {
            throw ValidationException::withMessages([
                'login' => ['This account cannot sign in. Contact support if you believe this is a mistake.'],
            ]);
        }

        $this->users->forceUpdate($user, ['last_login_at' => now(), 'last_login_ip' => $ip]);

        $token = $user->createToken($deviceName !== null && $deviceName !== '' ? $deviceName : 'api-token');

        return [$user, $token];
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

    public function resetPassword(User $user, VerificationCodeType $type, string $plainCode, string $newPassword): bool
    {
        if (! $this->verificationCodes->consume($user, $type, VerificationCodePurpose::PasswordReset, $plainCode)) {
            return false;
        }

        $this->users->forceUpdate($user, ['password' => $newPassword]);

        // A password reset invalidates every existing session token — a device that stayed logged
        // in with the old password should not silently keep working after a reset that was likely
        // triggered because the account was compromised.
        $user->tokens()->delete();

        $this->notifyPasswordChanged($user);

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

        // Sanctum's own stub types currentAccessToken() as non-nullable (@return TToken, no null
        // union), but the underlying $accessToken property genuinely is nullable — it's only
        // populated when $user was resolved via Sanctum authentication. Every current caller of
        // changePassword() passes an authenticated $request->user(), so this is safe today, but
        // the nullsafe stays as defensive correctness against the real (not documented) type.
        // @phpstan-ignore nullsafe.neverNull
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $this->notifyPasswordChanged($user);
    }

    private function notifyPasswordChanged(User $user): void
    {
        // Email-only, deliberately — see PasswordChangedNotification's docblock. A mobile-only
        // account with no email on file simply has no channel for this confirmation; that's a
        // data-availability fact, not a bug to work around with an SMS substitute.
        if ($user->email !== null) {
            $user->notify(new PasswordChangedNotification);
        }
    }
}
