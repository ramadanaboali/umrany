<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    public function test_wrong_current_password_is_rejected_and_account_is_not_deleted(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/core/auth/account', ['current_password' => 'WrongPassword'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_correct_password_soft_deletes_account_and_revokes_all_tokens(): void
    {
        $user = User::factory()->create(['password' => 'Secr3tPass']);
        $tokenA = $user->createToken('device-a');
        $tokenB = $user->createToken('device-b');

        $this->withHeader('Authorization', "Bearer {$tokenA->plainTextToken}")
            ->deleteJson('/api/v1/core/auth/account', ['current_password' => 'Secr3tPass'])
            ->assertStatus(204);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        // Every token — not just other devices — is revoked, unlike change-password which keeps
        // the requesting token alive. Deletion has no "current session stays valid" concept.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenA->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenB->accessToken->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deleted_account_login_is_rejected_with_the_same_generic_message_as_wrong_password(): void
    {
        $user = User::factory()->create(['email' => 'deleted@example.com', 'password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/core/auth/account', ['current_password' => 'Secr3tPass'])
            ->assertStatus(204);

        $deletedLogin = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'deleted@example.com',
            'password' => 'Secr3tPass',
        ]);

        $wrongPassword = $this->postJson('/api/v1/core/auth/login', [
            'login' => (User::factory()->create(['email' => 'real@example.com', 'password' => 'RealPass123']))->email,
            'password' => 'NotRealPass',
        ]);

        $deletedLogin->assertStatus(422)->assertJsonValidationErrors('login');

        // Same anti-enumeration message as any other wrong-password case — a soft-deleted account
        // must not be distinguishable from "wrong password on a real account" either.
        $this->assertSame(
            $wrongPassword->json('errors.login.0'),
            $deletedLogin->json('errors.login.0'),
        );
    }

    public function test_new_registration_can_reuse_email_after_account_deletion(): void
    {
        $user = User::factory()->create(['email' => 'reuse@example.com', 'mobile' => null, 'password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/core/auth/account', ['current_password' => 'Secr3tPass'])
            ->assertStatus(204);

        $this->postJson('/api/v1/core/auth/register', [
            'name' => 'New Owner',
            'email' => 'reuse@example.com',
            'password' => 'NewSecr3tPass',
            'password_confirmation' => 'NewSecr3tPass',
            'terms_accepted' => true,
        ])->assertStatus(201);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['email' => 'reuse@example.com', 'name' => 'New Owner', 'deleted_at' => null]);
    }

    public function test_new_registration_can_reuse_mobile_after_account_deletion(): void
    {
        $user = User::factory()->create(['email' => null, 'mobile' => '+966500000077', 'password' => 'Secr3tPass']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/core/auth/account', ['current_password' => 'Secr3tPass'])
            ->assertStatus(204);

        $this->postJson('/api/v1/core/auth/register', [
            'name' => 'New Owner',
            'mobile' => '+966500000077',
            'password' => 'NewSecr3tPass',
            'password_confirmation' => 'NewSecr3tPass',
            'terms_accepted' => true,
        ])->assertStatus(201);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['mobile' => '+966500000077', 'name' => 'New Owner', 'deleted_at' => null]);
    }
}
