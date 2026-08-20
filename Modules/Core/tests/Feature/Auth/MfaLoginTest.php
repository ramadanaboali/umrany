<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\MfaRecoveryCode;
use Modules\Core\Models\UserMfaSetting;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaLoginTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    public function test_login_with_confirmed_mfa_returns_challenge_and_issues_no_token(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.com', 'password' => 'Secr3tPass']);
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => self::SECRET,
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk();

        $response->assertJsonPath('data.mfa_required', true);
        $this->assertNotEmpty($response->json('data.challenge_token'));
        $this->assertSame(300, $response->json('data.expires_in'));
        $response->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_completing_challenge_with_valid_totp_code_completes_login(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.com', 'password' => 'Secr3tPass']);
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => self::SECRET,
            'confirmed_at' => now(),
        ]);

        $challengeToken = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->json('data.challenge_token');

        $code = app(Google2FA::class)->getCurrentOtp(self::SECRET);

        $response = $this->postJson('/api/v1/core/auth/mfa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ])->assertOk();

        $response->assertJsonPath('data.user.id', $user->id);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_completing_challenge_with_wrong_code_returns_generic_error(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.com', 'password' => 'Secr3tPass']);
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => self::SECRET,
            'confirmed_at' => now(),
        ]);

        $challengeToken = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->json('data.challenge_token');

        $this->postJson('/api/v1/core/auth/mfa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422)
            ->assertJson(['message' => 'This challenge is invalid or has expired.']);

        // Same generic message for a bogus/unknown challenge token — anti-enumeration.
        $this->postJson('/api/v1/core/auth/mfa/challenge', [
            'challenge_token' => 'not-a-real-token',
            'code' => '000000',
        ])->assertStatus(422)
            ->assertJson(['message' => 'This challenge is invalid or has expired.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unconfirmed_mfa_setting_does_not_gate_login(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.com', 'password' => 'Secr3tPass']);

        // An enrollment that was started but never confirmed via POST .../auth/mfa/confirm.
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => self::SECRET,
            'confirmed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk();

        // A direct (non-MFA) login response has no "mfa_required" key at all — it just returns
        // the normal auth payload straight away.
        $response->assertJsonMissingPath('data.mfa_required');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_reusing_the_same_totp_code_across_two_logins_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.com', 'password' => 'Secr3tPass']);
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => self::SECRET,
            'confirmed_at' => now(),
        ]);

        $code = app(Google2FA::class)->getCurrentOtp(self::SECRET);

        $firstChallengeToken = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->json('data.challenge_token');

        $this->postJson('/api/v1/core/auth/mfa/challenge', [
            'challenge_token' => $firstChallengeToken,
            'code' => $code,
        ])->assertOk();

        $secondChallengeToken = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->json('data.challenge_token');

        // Same TOTP time-slice, already consumed by the first login — replay is rejected even
        // though the code itself is otherwise well-formed and was genuinely valid moments ago.
        $this->postJson('/api/v1/core/auth/mfa/challenge', [
            'challenge_token' => $secondChallengeToken,
            'code' => $code,
        ])->assertStatus(422)
            ->assertJson(['message' => 'This challenge is invalid or has expired.']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_recovery_code_completes_challenge_and_is_single_use(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.com', 'password' => 'Secr3tPass']);
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => self::SECRET,
            'confirmed_at' => now(),
        ]);
        $recoveryCode = MfaRecoveryCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('test1-code1'),
        ]);

        $firstChallengeToken = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->json('data.challenge_token');

        $this->postJson('/api/v1/core/auth/mfa/challenge', [
            'challenge_token' => $firstChallengeToken,
            'code' => 'test1-code1',
        ])->assertOk();

        $this->assertNotNull($recoveryCode->refresh()->used_at);

        $secondChallengeToken = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa@example.com',
            'password' => 'Secr3tPass',
        ])->json('data.challenge_token');

        // The same recovery code cannot be used a second time.
        $this->postJson('/api/v1/core/auth/mfa/challenge', [
            'challenge_token' => $secondChallengeToken,
            'code' => 'test1-code1',
        ])->assertStatus(422)
            ->assertJson(['message' => 'This challenge is invalid or has expired.']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
