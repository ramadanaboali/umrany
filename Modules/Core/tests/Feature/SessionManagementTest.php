<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    public function test_can_list_active_sessions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device-1');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/core/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_name', 'device-1')
            ->assertJsonPath('data.0.is_current', true);
    }

    public function test_a_token_older_than_the_configured_expiration_is_excluded_from_the_listing(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device');
        $old = $user->createToken('old-device');

        $old->accessToken->forceFill([
            'created_at' => now()->subMinutes((int) config('sanctum.expiration') + 10),
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$current->plainTextToken)
            ->getJson('/api/v1/core/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_name', 'current-device');
    }

    public function test_can_revoke_a_specific_session(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device');
        $other = $user->createToken('other-device');

        $this->withHeader('Authorization', 'Bearer '.$current->plainTextToken)
            ->deleteJson('/api/v1/core/auth/sessions/'.$other->accessToken->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_cannot_revoke_a_session_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mine');

        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('theirs');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson('/api/v1/core/auth/sessions/'.$otherToken->accessToken->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_can_revoke_every_other_session_while_keeping_the_current_one(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('a');
        $tokenB = $user->createToken('b');
        $tokenC = $user->createToken('c');

        $this->withHeader('Authorization', 'Bearer '.$tokenB->plainTextToken)
            ->deleteJson('/api/v1/core/auth/sessions')
            ->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenB->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenA->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenC->accessToken->id]);
    }

    public function test_logout_all_revokes_every_session_including_the_one_used_to_authenticate(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('a');
        $tokenB = $user->createToken('b');

        $this->withHeader('Authorization', 'Bearer '.$tokenA->plainTextToken)
            ->postJson('/api/v1/core/auth/logout-all')
            ->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
