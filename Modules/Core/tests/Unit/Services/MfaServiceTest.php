<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\MfaRecoveryCode;
use Modules\Core\Models\UserMfaSetting;
use Modules\Core\Services\MfaService;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaServiceTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    public function test_regenerate_recovery_codes_returns_configured_count_each_independently_verifiable(): void
    {
        $user = $this->confirmedUser();
        $service = app(MfaService::class);

        $codes = $service->regenerateRecoveryCodes($user, $this->currentOtp());

        $configuredCount = (int) config('core.mfa.recovery_code_count');
        $this->assertCount($configuredCount, $codes);

        $hashes = MfaRecoveryCode::query()->where('user_id', $user->id)->pluck('code_hash');
        $this->assertCount($configuredCount, $hashes);

        foreach ($codes as $plainCode) {
            $this->assertTrue($hashes->contains(fn ($hash) => Hash::check($plainCode, $hash)));
        }
    }

    public function test_regenerating_recovery_codes_invalidates_every_previously_issued_code(): void
    {
        $user = $this->confirmedUser();
        $service = app(MfaService::class);

        $firstCodes = $service->regenerateRecoveryCodes($user, $this->currentOtp());

        // Regenerating again (with a fresh, not-yet-replayed TOTP code) wipes the whole set, so
        // every unused code from the old batch stops working, even though none of them were
        // individually used up.
        $secondCodes = $service->regenerateRecoveryCodes($user, $this->nextOtp());

        foreach ($firstCodes as $oldCode) {
            $this->assertFalse($service->verifyCode($user, $oldCode));
        }

        foreach ($secondCodes as $newCode) {
            $this->assertTrue($service->verifyCode($user, $newCode));
        }
    }

    public function test_verify_code_accepts_a_valid_totp_code(): void
    {
        $user = $this->confirmedUser();
        $service = app(MfaService::class);

        $this->assertTrue($service->verifyCode($user, $this->currentOtp()));
    }

    public function test_verify_code_rejects_a_replayed_totp_code(): void
    {
        $user = $this->confirmedUser();
        $service = app(MfaService::class);
        $code = $this->currentOtp();

        $this->assertTrue($service->verifyCode($user, $code));
        // Same time-slice submitted again — replay protection rejects it even though it was
        // genuinely valid moments ago.
        $this->assertFalse($service->verifyCode($user, $code));
    }

    public function test_verify_code_accepts_and_consumes_a_recovery_code(): void
    {
        $user = $this->confirmedUser();
        MfaRecoveryCode::query()->create(['user_id' => $user->id, 'code_hash' => Hash::make('test1-code1')]);
        $service = app(MfaService::class);

        $this->assertTrue($service->verifyCode($user, 'test1-code1'));
        $this->assertFalse($service->verifyCode($user, 'test1-code1'));
    }

    public function test_issue_challenge_then_consume_challenge_round_trips_device_and_ip(): void
    {
        $user = $this->confirmedUser();
        $service = app(MfaService::class);

        $token = $service->issueChallenge($user, 'iphone-15', '203.0.113.5');

        $result = $service->consumeChallenge($token, $this->currentOtp());

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result['user_id']);
        $this->assertSame('iphone-15', $result['device_name']);
        $this->assertSame('203.0.113.5', $result['ip']);
    }

    public function test_challenge_survives_up_to_but_not_including_max_attempts_wrong_codes(): void
    {
        $user = $this->confirmedUser();
        $service = app(MfaService::class);
        $maxAttempts = (int) config('core.mfa.challenge_max_attempts');

        $token = $service->issueChallenge($user, null, null);

        for ($i = 0; $i < $maxAttempts - 1; $i++) {
            $this->assertNull($service->consumeChallenge($token, '000000'));
        }

        // One attempt short of the cap — the challenge is still alive and a correct code succeeds.
        $this->assertNotNull($service->consumeChallenge($token, $this->currentOtp()));
    }

    public function test_challenge_is_permanently_invalidated_once_max_attempts_wrong_codes_are_submitted(): void
    {
        $user = $this->confirmedUser();
        $service = app(MfaService::class);
        $maxAttempts = (int) config('core.mfa.challenge_max_attempts');

        $token = $service->issueChallenge($user, null, null);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->assertNull($service->consumeChallenge($token, '000000'));
        }

        // The cap has been hit — even a subsequently-correct code is rejected because the
        // challenge cache entry was forgotten once attempts reached the configured maximum.
        $this->assertNull($service->consumeChallenge($token, $this->currentOtp()));
    }

    private function confirmedUser(): User
    {
        $user = User::factory()->create();

        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => self::SECRET,
            'confirmed_at' => now(),
        ]);

        return $user;
    }

    private function currentOtp(): string
    {
        return app(Google2FA::class)->getCurrentOtp(self::SECRET);
    }

    /**
     * A code for the following time-slice — still within the default verification window, and
     * guaranteed distinct from currentOtp() so it isn't rejected as a replay.
     */
    private function nextOtp(): string
    {
        $google2fa = app(Google2FA::class);

        return $google2fa->oathTotp(self::SECRET, $google2fa->getTimestamp() + 1);
    }
}
