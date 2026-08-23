<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\VerificationCode;
use Modules\Core\Notifications\PasswordResetCompletedNotification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    /**
     * Deliberate reversal, per explicit product decision — see docs/decisions/0011-admin-forgot-
     * password-reveals-account-existence.md's superseding note. This endpoint now rejects an
     * unknown login instead of returning the previous generic success message.
     */
    public function test_forgot_password_rejects_an_unknown_login_by_name(): void
    {
        $this->postJson('/api/v1/core/auth/forgot-password', ['login' => 'nobody@example.com'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'No account exists with that email or mobile number.');
    }

    public function test_forgot_password_rejects_a_soft_deleted_accounts_identifier(): void
    {
        $user = User::factory()->create(['email' => 'deleted@example.com']);
        $user->delete();

        $this->postJson('/api/v1/core/auth/forgot-password', ['login' => 'deleted@example.com'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'No account exists with that email or mobile number.');
    }

    public function test_forgot_password_issues_a_reset_code_for_a_real_account(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com']);

        $this->postJson('/api/v1/core/auth/forgot-password', ['login' => 'ahmed@example.com'])->assertOk();

        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $user->id,
            'purpose' => 'password_reset',
        ]);
    }

    public function test_reset_password_with_correct_code_changes_the_password_and_revokes_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'OldPassword1']);
        $user->createToken('old-device');

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::PasswordReset,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => 'ahmed@example.com',
            'code' => '654321',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword1', $user->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Notification::assertSentTo($user, PasswordResetCompletedNotification::class);
    }

    public function test_reset_password_with_wrong_code_fails_without_leaking_account_existence(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com']);

        $knownAccount = $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => 'ahmed@example.com',
            'code' => '000000',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $unknownAccount = $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => 'nobody@example.com',
            'code' => '000000',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $knownAccount->assertStatus(422);
        $unknownAccount->assertStatus(422);
        $this->assertSame($knownAccount->json('message'), $unknownAccount->json('message'));
    }

    public function test_a_mobile_only_account_can_reset_its_password_via_mobile_channel(): void
    {
        $user = User::factory()->create(['email' => null, 'mobile' => '+966500000009']);

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Mobile,
            'purpose' => VerificationCodePurpose::PasswordReset,
            'code_hash' => Hash::make('111222'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => '+966500000009',
            'code' => '111222',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword1', $user->refresh()->password));
    }
}
