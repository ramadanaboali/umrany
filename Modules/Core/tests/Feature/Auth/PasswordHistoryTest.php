<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Models\PasswordHistory;
use Modules\Core\Models\VerificationCode;
use Tests\TestCase;

/**
 * Password-reuse enforcement shared between the authenticated change-password flow (PUT
 * .../auth/password — rejected at the FormRequest layer via Modules\Core\Rules\NotAPreviousPassword)
 * and the unauthenticated OTP reset flow (POST .../auth/reset-password — checked inside
 * Modules\Core\Services\AuthService::resetPassword() only AFTER the code is consumed, so a wrong
 * code never leaks whether the attempted password happens to be a previously used one). See
 * Modules\Core\Services\PasswordHistoryService and
 * docs/decisions/0013-config-driven-password-policy-and-history.md.
 */
class PasswordHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Both /auth/password and /auth/reset-password share the same 'password-reset' named rate
        // limiter (keyed only by IP, 3/minute — Illuminate\Routing\Middleware\ThrottleRequests
        // hashes limiter-name + key, not per-route). Several tests below deliberately make more
        // than 3 requests in a row to prove reuse boundaries, so the limiter must not interfere.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_changing_password_to_the_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);
        $token = $user->createToken('device-a')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'OldPassword1',
                'password' => 'OldPassword1',
                'password_confirmation' => 'OldPassword1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('OldPassword1', $user->refresh()->password));
    }

    public function test_reusing_a_password_within_history_count_is_rejected_but_an_older_one_is_allowed(): void
    {
        config(['core.password_policy.history_count' => 2]);

        $user = User::factory()->create(['password' => 'PasswordD1']);

        // Seed the history table directly with explicit, well-separated `created_at` values —
        // mirrors PasswordResetTest seeding VerificationCode rows directly rather than driving the
        // whole flow through HTTP. This sidesteps relying on real elapsed wall-clock time between
        // requests to get distinct ordering, since password_histories.created_at only has
        // second-level (DB `useCurrent()`) resolution and several requests here could easily land
        // in the same second.
        foreach ([
            ['password' => 'PasswordC1', 'created_at' => now()->subMinute()],
            ['password' => 'PasswordB1', 'created_at' => now()->subMinutes(2)],
            ['password' => 'PasswordA1', 'created_at' => now()->subMinutes(3)],
        ] as $entry) {
            PasswordHistory::create([
                'authenticatable_type' => $user->getMorphClass(),
                'authenticatable_id' => $user->id,
                'password_hash' => Hash::make($entry['password']),
                'created_at' => $entry['created_at'],
            ]);
        }

        $token = $user->createToken('device-a')->plainTextToken;

        $attempt = fn (string $password) => $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'PasswordD1',
                'password' => $password,
                'password_confirmation' => $password,
            ]);

        // The live current password — always checked directly, independent of history_count.
        $attempt('PasswordD1')->assertStatus(422)->assertJsonValidationErrors('password');

        // Within the last 2 recorded hashes (C, B) — rejected.
        $attempt('PasswordC1')->assertStatus(422)->assertJsonValidationErrors('password');
        $attempt('PasswordB1')->assertStatus(422)->assertJsonValidationErrors('password');

        // Older than history_count=2 (3rd back) — allowed.
        $attempt('PasswordA1')->assertOk();
        $this->assertTrue(Hash::check('PasswordA1', $user->refresh()->password));
    }

    public function test_history_count_zero_disables_the_reuse_check(): void
    {
        config(['core.password_policy.history_count' => 0]);

        $user = User::factory()->create(['password' => 'OldPassword1']);
        $token = $user->createToken('device-a')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'OldPassword1',
                'password' => 'OldPassword1',
                'password_confirmation' => 'OldPassword1',
            ])
            ->assertOk();
    }

    public function test_reset_password_wrong_code_gives_the_same_generic_message_whether_or_not_the_new_password_was_reused(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'OldPassword1']);

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::PasswordReset,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $reusedAttempt = $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => 'ahmed@example.com',
            'code' => '000000',
            'password' => 'OldPassword1',
            'password_confirmation' => 'OldPassword1',
        ]);

        $newPasswordAttempt = $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => 'ahmed@example.com',
            'code' => '000000',
            'password' => 'BrandNewPassword1',
            'password_confirmation' => 'BrandNewPassword1',
        ]);

        // Neither attempt is a validation-style error at all — both must be the exact same plain
        // generic response the controller returns directly for a bad code, never a
        // ValidationException's {"errors": {...}} shape (that would leak "this password was used
        // before," i.e. that the account/password pair is real, from a wrong code alone).
        $reusedAttempt->assertStatus(422)->assertJsonMissingValidationErrors('password');
        $newPasswordAttempt->assertStatus(422)->assertJsonMissingValidationErrors('password');
        $this->assertArrayNotHasKey('errors', $reusedAttempt->json());
        $this->assertArrayNotHasKey('errors', $newPasswordAttempt->json());

        $this->assertSame('This code is invalid or has expired.', $reusedAttempt->json('message'));
        $this->assertSame($reusedAttempt->json('message'), $newPasswordAttempt->json('message'));

        $this->assertTrue(Hash::check('OldPassword1', $user->refresh()->password));
    }

    public function test_reset_password_with_a_correct_code_rejects_a_reused_password_distinctly_and_accepts_a_new_one(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'OldPassword1']);

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::PasswordReset,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Correct code, but the "new" password is the account's current one — rejected, and
        // distinctly so: a real validation error on the password field, not the generic message
        // above (the code has already been proven correct at this point, so there is nothing left
        // to hide).
        $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => 'ahmed@example.com',
            'code' => '654321',
            'password' => 'OldPassword1',
            'password_confirmation' => 'OldPassword1',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertTrue(
            Hash::check('OldPassword1', $user->refresh()->password),
            'the reused-password attempt must not have changed the stored password',
        );

        // That attempt already consumed the code (verified, in VerificationCodeService::consume(),
        // before the reuse check ever runs) — a fresh code is needed to prove a correct code plus a
        // genuinely new password succeeds.
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => VerificationCodeType::Email,
            'purpose' => VerificationCodePurpose::PasswordReset,
            'code_hash' => Hash::make('111111'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/core/auth/reset-password', [
            'login' => 'ahmed@example.com',
            'code' => '111111',
            'password' => 'BrandNewPassword1',
            'password_confirmation' => 'BrandNewPassword1',
        ])->assertOk();

        $this->assertTrue(Hash::check('BrandNewPassword1', $user->refresh()->password));
    }
}
