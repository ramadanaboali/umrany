<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\MfaRecoveryCode;
use Modules\Core\Models\UserMfaSetting;
use Modules\Core\Notifications\MfaStateChangedNotification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaEnrollmentTest extends TestCase
{
    public function test_mfa_status_defaults_to_disabled_for_a_fresh_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/auth/mfa')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.confirmed_at', null)
            ->assertJsonPath('data.recovery_codes_remaining', 0);
    }

    public function test_enabling_mfa_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa', ['current_password' => 'WrongPassword'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertDatabaseCount('user_mfa_settings', 0);
    }

    public function test_enabling_mfa_creates_an_unconfirmed_setting_that_does_not_show_as_enabled(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa', ['current_password' => 'Secr3tPass'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_uri', 'qr_svg']]);

        $this->assertNotEmpty($response->json('data.secret'));
        $this->assertDatabaseCount('user_mfa_settings', 1);

        $setting = UserMfaSetting::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($setting);
        $this->assertNull($setting->confirmed_at);

        // An unconfirmed enrollment must never present as "enabled" — an abandoned enrollment
        // can never lock the account out.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/auth/mfa')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_confirming_enrollment_activates_mfa_and_returns_recovery_codes_not_stored_in_plaintext(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $secret = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa', ['current_password' => 'Secr3tPass'])
            ->json('data.secret');

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa/confirm', ['code' => $code])
            ->assertOk();

        $recoveryCodes = $response->json('data.recovery_codes');

        $this->assertCount((int) config('core.mfa.recovery_code_count'), $recoveryCodes);

        $setting = UserMfaSetting::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($setting->confirmed_at);

        $hashes = MfaRecoveryCode::query()->where('user_id', $user->id)->pluck('code_hash');
        $this->assertCount(count($recoveryCodes), $hashes);

        foreach ($recoveryCodes as $plainCode) {
            // The plaintext code must never appear verbatim in the stored hash column...
            $this->assertNotContains($plainCode, $hashes->all());
            // ...but it must independently verify against exactly one stored hash.
            $this->assertTrue($hashes->contains(fn ($hash) => Hash::check($plainCode, $hash)));
        }

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/auth/mfa')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.recovery_codes_remaining', count($recoveryCodes));

        Notification::assertSentTo($user, MfaStateChangedNotification::class);
    }

    public function test_confirming_enrollment_with_wrong_code_fails(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa', ['current_password' => 'Secr3tPass'])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $setting = UserMfaSetting::query()->where('user_id', $user->id)->first();
        $this->assertNull($setting->confirmed_at);
    }

    public function test_confirming_without_a_pending_enrollment_fails(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa/confirm', ['code' => '123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_cannot_begin_enrollment_when_mfa_is_already_confirmed(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => 'JBSWY3DPEHPK3PXP',
            'confirmed_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa', ['current_password' => 'Secr3tPass'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mfa');
    }

    public function test_disabling_mfa_requires_current_password_and_a_valid_code(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $secret = 'JBSWY3DPEHPK3PXP';
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => $secret,
            'confirmed_at' => now(),
        ]);

        // Wrong current_password.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/core/auth/mfa', [
                'current_password' => 'WrongPassword',
                'code' => app(Google2FA::class)->getCurrentOtp($secret),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        // Right password, wrong code.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/core/auth/mfa', [
                'current_password' => 'Secr3tPass',
                'code' => '000000',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('user_mfa_settings', 1);
    }

    public function test_disabling_mfa_succeeds_and_removes_setting_and_recovery_codes(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $secret = 'JBSWY3DPEHPK3PXP';
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => $secret,
            'confirmed_at' => now(),
        ]);
        MfaRecoveryCode::query()->create(['user_id' => $user->id, 'code_hash' => Hash::make('test1-code1')]);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/core/auth/mfa', [
                'current_password' => 'Secr3tPass',
                'code' => $code,
            ])
            ->assertStatus(204);

        $this->assertDatabaseCount('user_mfa_settings', 0);
        $this->assertDatabaseCount('mfa_recovery_codes', 0);

        Notification::assertSentTo($user, MfaStateChangedNotification::class);
    }

    public function test_regenerating_recovery_codes_returns_new_codes_and_invalidates_old_ones(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $secret = 'JBSWY3DPEHPK3PXP';
        UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => $secret,
            'confirmed_at' => now(),
        ]);
        $oldCode = MfaRecoveryCode::query()->create(['user_id' => $user->id, 'code_hash' => Hash::make('old11-code1')]);

        $totp = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/mfa/recovery-codes', [
                'current_password' => 'Secr3tPass',
                'code' => $totp,
            ])
            ->assertOk();

        $newCodes = $response->json('data.recovery_codes');
        $this->assertCount((int) config('core.mfa.recovery_code_count'), $newCodes);

        // The old recovery code row is gone entirely — regenerating replaces the whole set.
        $this->assertDatabaseMissing('mfa_recovery_codes', ['id' => $oldCode->id]);
        $this->assertDatabaseCount('mfa_recovery_codes', count($newCodes));

        foreach ($newCodes as $plainCode) {
            $this->assertFalse(Hash::check($plainCode, $oldCode->code_hash));
        }
    }
}
