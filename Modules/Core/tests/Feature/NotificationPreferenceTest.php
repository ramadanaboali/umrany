<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Enums\NotificationEvent;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    public function test_get_returns_all_eight_events_with_correct_defaults_for_a_user_with_no_stored_preferences(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/notification-preferences')
            ->assertOk();

        $data = Collection::make($response->json('data'));

        $this->assertCount(8, $data);
        $this->assertSame(NotificationEvent::values(), $data->pluck('event_type')->all());

        foreach (NotificationEvent::cases() as $event) {
            $row = $data->firstWhere('event_type', $event->value);

            $this->assertNotNull($row);
            $this->assertSame($event->isMandatory(), $row['mandatory']);
            // Both channels default on for every event — a zero-row user still gets a complete,
            // correct matrix rather than nulls/false.
            $this->assertTrue($row['in_app']);
            $this->assertTrue($row['email']);
        }
    }

    public function test_put_updates_a_non_mandatory_events_channels_and_a_subsequent_get_reflects_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $this->assertFalse(NotificationEvent::NewDeviceLogin->isMandatory());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/me/notification-preferences', [
                'preferences' => [
                    ['event_type' => 'new_device_login', 'in_app' => true, 'email' => false],
                ],
            ])
            ->assertOk()
            ->assertJsonPath(
                'data',
                fn (array $data) => Collection::make($data)->firstWhere('event_type', 'new_device_login')['email'] === false,
            );

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/notification-preferences')
            ->assertOk();

        $row = Collection::make($response->json('data'))->firstWhere('event_type', 'new_device_login');

        $this->assertTrue($row['in_app']);
        $this->assertFalse($row['email']);
    }

    public function test_put_attempting_to_disable_a_mandatory_event_is_silently_forced_back_to_true(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test')->plainTextToken;

        $this->assertTrue(NotificationEvent::PasswordChanged->isMandatory());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/me/notification-preferences', [
                'preferences' => [
                    ['event_type' => 'password_changed', 'in_app' => false, 'email' => false],
                ],
            ])
            ->assertOk();

        $row = Collection::make($response->json('data'))->firstWhere('event_type', 'password_changed');

        $this->assertTrue($row['mandatory']);
        $this->assertTrue($row['in_app']);
        $this->assertTrue($row['email']);
    }
}
