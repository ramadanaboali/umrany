<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\UserMfaSetting;
use Modules\Core\Notifications\MfaStateChangedNotification;
use Modules\Core\Repositories\Contracts\UserMfaRepositoryInterface;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP MFA — enrollment (registration-time or profile-driven), confirmation, disable, recovery
 * codes, and the login-time challenge/response exchange. See
 * docs/decisions/0012-totp-mfa-with-two-step-login.md.
 */
final class MfaService
{
    private const CHALLENGE_CACHE_PREFIX = 'umrany:core:mfa-challenge:';

    public function __construct(
        private readonly UserMfaRepositoryInterface $mfa,
        private readonly Google2FA $google2fa,
    ) {}

    /**
     * @return array{enabled: bool, confirmed_at: ?CarbonInterface, recovery_codes_remaining: int}
     */
    public function status(User $user): array
    {
        $setting = $this->mfa->findSettingForUser($user);
        $enabled = $setting !== null && $setting->isConfirmed();

        return [
            'enabled' => $enabled,
            'confirmed_at' => $setting?->confirmed_at,
            'recovery_codes_remaining' => $enabled ? $this->mfa->unusedRecoveryCodes($user)->count() : 0,
        ];
    }

    /**
     * Generates and stores a new, unconfirmed secret — replacing any prior unconfirmed one.
     * Never touches an already-confirmed setting; call disable() first if the caller wants to
     * re-enroll from scratch.
     *
     * @return array{setting: UserMfaSetting, otpauth_uri: string, qr_svg: string}
     *
     * @throws ValidationException MFA is already enabled and confirmed.
     */
    public function beginEnrollment(User $user): array
    {
        $existing = $this->mfa->findSettingForUser($user);

        if ($existing !== null && $existing->isConfirmed()) {
            throw ValidationException::withMessages([
                'mfa' => ['MFA is already enabled on this account. Disable it first to re-enroll.'],
            ]);
        }

        $secret = $this->google2fa->generateSecretKey();
        $setting = $this->mfa->replaceSetting($user, $secret);

        $otpauthUri = $this->google2fa->getQRCodeUrl(
            config('core.mfa.issuer'),
            $user->email ?? $user->mobile ?? (string) $user->id,
            $secret,
        );

        return [
            'setting' => $setting,
            'otpauth_uri' => $otpauthUri,
            'qr_svg' => $this->renderQrSvg($otpauthUri),
        ];
    }

    /**
     * Activates a pending enrollment and mints the one-time-visible recovery code set. Enrollment
     * is never enforced at login until this succeeds — see App\Models\User::hasMfaEnabled().
     *
     * @return array<int, string> Plaintext recovery codes — visible exactly once.
     *
     * @throws ValidationException No pending enrollment, or an invalid code.
     */
    public function confirm(User $user, string $code): array
    {
        $setting = $this->mfa->findSettingForUser($user);

        if ($setting === null || $setting->isConfirmed()) {
            throw ValidationException::withMessages([
                'code' => ['There is no pending MFA enrollment to confirm.'],
            ]);
        }

        $result = $this->verifyTotp($setting, $code);

        if ($result === false) {
            throw ValidationException::withMessages([
                'code' => ['This code is invalid.'],
            ]);
        }

        $this->mfa->confirmSetting($setting);
        $this->mfa->updateLastUsedTimestamp($setting, $result);

        $recoveryCodes = $this->regenerateRecoveryCodesFor($user);

        $user->notify(new MfaStateChangedNotification(enabled: true));

        return $recoveryCodes;
    }

    /**
     * Requires a valid TOTP/recovery code (in addition to the current-password check already
     * enforced by DisableMfaRequest) — a stolen session alone must never be able to silently turn
     * MFA off.
     *
     * @throws ValidationException Invalid code.
     */
    public function disable(User $user, string $code): void
    {
        $setting = $this->requireConfirmedSetting($user);

        if (! $this->verifyCode($user, $code)) {
            throw ValidationException::withMessages([
                'code' => ['This code is invalid.'],
            ]);
        }

        $this->mfa->deleteSetting($setting);
        $this->mfa->replaceRecoveryCodes($user, []);

        $user->notify(new MfaStateChangedNotification(enabled: false));
    }

    /**
     * @return array<int, string> Plaintext recovery codes — visible exactly once.
     *
     * @throws ValidationException Invalid code.
     */
    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        $this->requireConfirmedSetting($user);

        if (! $this->verifyCode($user, $code)) {
            throw ValidationException::withMessages([
                'code' => ['This code is invalid.'],
            ]);
        }

        return $this->regenerateRecoveryCodesFor($user);
    }

    /**
     * Verifies a TOTP or recovery code against a CONFIRMED setting — used by the login challenge
     * and by disable()/regenerateRecoveryCodes() (both require re-proving possession, not just
     * the current-password check already done by their FormRequests).
     */
    public function verifyCode(User $user, string $code): bool
    {
        $setting = $this->mfa->findSettingForUser($user);

        if ($setting === null || ! $setting->isConfirmed()) {
            return false;
        }

        if ($this->verifyTotp($setting, $code) !== false) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * Step 1 of login when MFA is enabled — no Sanctum token is issued yet. Returns an opaque
     * challenge token the client presents to consumeChallenge() (step 2). Cache key is the
     * SHA-256 of the token, not the token itself, so a cache dump never hands out live challenges.
     */
    public function issueChallenge(User $user, ?string $deviceName, ?string $ip): string
    {
        $token = Str::random(64);

        Cache::put(
            $this->challengeCacheKey($token),
            ['user_id' => $user->id, 'device_name' => $deviceName, 'ip' => $ip, 'attempts' => 0],
            now()->addSeconds(config('core.mfa.challenge_ttl_seconds')),
        );

        return $token;
    }

    /**
     * Step 2. Returns the original device_name/ip on success so the caller can finish issuing the
     * session exactly as a direct login would. One generic failure mode for both "expired/unknown
     * token" and "wrong code" — preserving the existing no-enumeration posture (see
     * Modules\Core\Services\AuthService::login()).
     *
     * @return array{user_id: int, device_name: ?string, ip: ?string}|null
     */
    public function consumeChallenge(string $challengeToken, string $code): ?array
    {
        $key = $this->challengeCacheKey($challengeToken);
        $challenge = Cache::get($key);

        if (! is_array($challenge)) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->find($challenge['user_id']);

        if ($user === null || ! $this->verifyCode($user, $code)) {
            $challenge['attempts']++;

            if ($challenge['attempts'] >= config('core.mfa.challenge_max_attempts')) {
                Cache::forget($key);
            } else {
                Cache::put($key, $challenge, now()->addSeconds(config('core.mfa.challenge_ttl_seconds')));
            }

            return null;
        }

        Cache::forget($key);

        return [
            'user_id' => $challenge['user_id'],
            'device_name' => $challenge['device_name'],
            'ip' => $challenge['ip'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function regenerateRecoveryCodesFor(User $user): array
    {
        $count = (int) config('core.mfa.recovery_code_count');
        $plainCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < $count; $i++) {
            $plain = Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5));
            $plainCodes[] = $plain;
            $hashedCodes[] = Hash::make($plain);
        }

        $this->mfa->replaceRecoveryCodes($user, $hashedCodes);

        return $plainCodes;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        foreach ($this->mfa->unusedRecoveryCodes($user) as $recoveryCode) {
            if (Hash::check($code, $recoveryCode->code_hash)) {
                $this->mfa->markRecoveryCodeUsed($recoveryCode);

                return true;
            }
        }

        return false;
    }

    /**
     * verifyKeyNewer() returns the matched time-slice counter (an int, possibly 0 — never treat
     * it as falsy) on success or `false` on failure. Passing `last_used_timestamp ?? 0` as the
     * floor is safe even on a setting's very first verification: the current real TOTP counter
     * (unix time / 30) is astronomically larger than 0.
     */
    private function verifyTotp(UserMfaSetting $setting, string $code): int|false
    {
        $result = $this->google2fa->verifyKeyNewer(
            $setting->secret,
            $code,
            $setting->last_used_timestamp ?? 0,
            config('core.mfa.window'),
        );

        if ($result !== false) {
            $this->mfa->updateLastUsedTimestamp($setting, (int) $result);
        }

        return $result === false ? false : (int) $result;
    }

    private function requireConfirmedSetting(User $user): UserMfaSetting
    {
        $setting = $this->mfa->findSettingForUser($user);

        if ($setting === null || ! $setting->isConfirmed()) {
            throw ValidationException::withMessages([
                'mfa' => ['MFA is not enabled on this account.'],
            ]);
        }

        return $setting;
    }

    private function renderQrSvg(string $otpauthUri): string
    {
        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($otpauthUri);
    }

    private function challengeCacheKey(string $token): string
    {
        return self::CHALLENGE_CACHE_PREFIX.hash('sha256', $token);
    }
}
