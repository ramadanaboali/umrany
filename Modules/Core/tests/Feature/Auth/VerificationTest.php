<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\VerificationCode;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    private function authenticatedUser(): array
    {
        // The stock UserFactory defaults email_verified_at to now() — override it so tests that
        // assert "still unverified" start from an actually-unverified account.
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'email_verified_at' => null]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    public function test_correct_code_verifies_the_account(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $code = VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::AccountVerification,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/verify', ['type' => 'email', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.user.email_verified', true);

        $this->assertNotNull($code->refresh()->consumed_at);
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    /**
     * Regression test for docs/decisions/0026-block-login-until-account-verified.md — the first
     * successful verification promotes a still-pending account to Active, mirroring what real
     * registration produces (PendingVerification) rather than the shared `authenticatedUser()`
     * helper's status='active' DB-default fixture.
     */
    public function test_first_verification_promotes_a_pending_account_to_active(): void
    {
        $user = User::factory()->create([
            'email' => 'pending@example.com',
            'email_verified_at' => null,
            'status' => UserStatus::PendingVerification,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::AccountVerification,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/verify', ['type' => 'email', 'code' => '123456'])
            ->assertOk();

        $this->assertSame(UserStatus::Active, $user->refresh()->status);
    }

    public function test_wrong_code_is_rejected_and_counts_as_an_attempt(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $code = VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::AccountVerification,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/verify', ['type' => 'email', 'code' => '000000'])
            ->assertStatus(422);

        $this->assertSame(1, $code->refresh()->attempts);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        [$user, $token] = $this->authenticatedUser();

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::AccountVerification,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/verify', ['type' => 'email', 'code' => '123456'])
            ->assertStatus(422);
    }

    public function test_code_stops_working_after_five_wrong_attempts(): void
    {
        [$user, $token] = $this->authenticatedUser();

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::AccountVerification,
            'code_hash' => Hash::make('123456'),
            'attempts' => 5,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Even the CORRECT code must now be rejected — the attempt budget is exhausted.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/verify', ['type' => 'email', 'code' => '123456'])
            ->assertStatus(422);
    }

    public function test_resend_rejects_a_channel_not_on_the_account(): void
    {
        [, $token] = $this->authenticatedUser(); // this user has no mobile

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/resend-code', ['type' => 'mobile'])
            ->assertStatus(422);
    }

    public function test_resend_rejects_an_already_verified_channel(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'email_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/resend-code', ['type' => 'email'])
            ->assertStatus(422);
    }
}
