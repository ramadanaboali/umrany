<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_login_is_rate_limited_after_five_attempts_per_minute(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secr3tPass']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/core/auth/login', [
                'login' => 'ahmed@example.com',
                'password' => 'WrongPassword',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'ahmed@example.com',
            'password' => 'Secr3tPass', // even the CORRECT password is now throttled
        ])->assertStatus(429);
    }

    public function test_resending_a_verification_code_is_limited_to_once_per_minute(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'email_verified_at' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/resend-code', ['type' => 'email'])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/auth/resend-code', ['type' => 'email'])
            ->assertStatus(429);
    }
}
