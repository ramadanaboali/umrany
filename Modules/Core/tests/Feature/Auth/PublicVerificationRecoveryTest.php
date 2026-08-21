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

/**
 * The recovery path for an account that lost its only session (the one register() issued)
 * before ever verifying — since login() now rejects an unverified account outright, this is
 * otherwise a permanent lockout. See docs/decisions/0026-block-login-until-account-verified.md.
 */
class PublicVerificationRecoveryTest extends TestCase
{
    private function pendingUser(string $email = 'pending@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => null,
            'status' => UserStatus::PendingVerification,
        ]);
    }

    public function test_resend_returns_the_same_generic_message_for_a_known_pending_account(): void
    {
        $this->pendingUser();

        $this->postJson('/api/v1/core/auth/resend-verification', ['login' => 'pending@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that account exists and needs verification, a code has been sent.');

        $this->assertDatabaseCount('verification_codes', 1);
    }

    public function test_resend_returns_the_same_generic_message_for_an_unknown_login_and_sends_nothing(): void
    {
        $this->postJson('/api/v1/core/auth/resend-verification', ['login' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that account exists and needs verification, a code has been sent.');

        $this->assertDatabaseCount('verification_codes', 0);
    }

    public function test_resend_is_a_silent_no_op_for_an_already_verified_account(): void
    {
        User::factory()->create(['email' => 'already-verified@example.com', 'email_verified_at' => now()]);

        $this->postJson('/api/v1/core/auth/resend-verification', ['login' => 'already-verified@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that account exists and needs verification, a code has been sent.');

        $this->assertDatabaseCount('verification_codes', 0);
    }

    public function test_verify_account_public_completes_verification_and_promotes_status(): void
    {
        $user = $this->pendingUser();

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::AccountVerification,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/core/auth/verify-account', ['login' => 'pending@example.com', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('message', 'Account verified. Please sign in.');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(UserStatus::Active, $user->status);

        // The whole point — login now succeeds via the normal endpoint.
        $this->postJson('/api/v1/core/auth/login', ['login' => 'pending@example.com', 'password' => 'password'])
            ->assertOk();
    }

    public function test_verify_account_public_rejects_wrong_code_with_the_generic_message(): void
    {
        $user = $this->pendingUser();

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::AccountVerification,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/core/auth/verify-account', ['login' => 'pending@example.com', 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This code is invalid or has expired.');
    }

    public function test_verify_account_public_rejects_an_unknown_login_with_the_same_generic_message(): void
    {
        $this->postJson('/api/v1/core/auth/verify-account', ['login' => 'nobody@example.com', 'code' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This code is invalid or has expired.');
    }
}
