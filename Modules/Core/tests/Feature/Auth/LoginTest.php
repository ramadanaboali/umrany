<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_can_log_in_with_email(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'ahmed@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_can_log_in_with_mobile(): void
    {
        User::factory()->create(['mobile' => '+966500000002', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => '+966500000002',
            'password' => 'Secr3tPass',
        ])->assertOk();
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'ahmed@example.com',
            'password' => 'WrongPassword',
        ])->assertStatus(422)->assertJsonValidationErrors('login');
    }

    public function test_unknown_login_is_rejected_with_the_same_generic_message_as_wrong_password(): void
    {
        $unknown = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'nobody@example.com',
            'password' => 'WhateverPass1',
        ]);
        $wrongPassword = $this->postJson('/api/v1/core/auth/login', [
            'login' => (User::factory()->create(['email' => 'real@example.com', 'password' => 'RealPass123']))->email,
            'password' => 'NotRealPass',
        ]);

        // Same message for "no such account" and "wrong password" — anti-enumeration.
        $this->assertSame(
            $unknown->json('errors.login.0'),
            $wrongPassword->json('errors.login.0'),
        );
    }

    public function test_suspended_account_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'Secr3tPass',
            'status' => UserStatus::Suspended,
        ]);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'suspended@example.com',
            'password' => 'Secr3tPass',
        ])->assertStatus(422)->assertJsonValidationErrors('login');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/core/auth/logout')
            ->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
