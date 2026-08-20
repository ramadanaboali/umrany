<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Notifications\NewDeviceLoginNotification;
use Modules\Core\Notifications\PasswordChangedNotification;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    public function test_disabling_new_device_logins_email_preference_sends_database_only_on_a_genuinely_new_device(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/me/notification-preferences', [
                'preferences' => [
                    ['event_type' => 'new_device_login', 'in_app' => true, 'email' => false],
                ],
            ])
            ->assertOk();

        Notification::fake();

        $this->withHeader('User-Agent', 'test-agent-1')
            ->postJson('/api/v1/core/auth/login', [
                'login' => $user->email,
                'password' => 'Passw0rd1!',
                'device_name' => 'Device A',
            ])
            ->assertOk();

        Notification::assertSentTo(
            $user,
            NewDeviceLoginNotification::class,
            fn ($notification, array $channels) => in_array('database', $channels, true) && ! in_array('mail', $channels, true),
        );
    }

    public function test_mandatory_event_still_sends_on_every_channel_even_after_a_client_attempted_to_disable_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);
        $token = $user->createToken('test')->plainTextToken;

        // Client attempts to silence a mandatory event before it fires — update() silently
        // forces both channels back on rather than rejecting the request.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/me/notification-preferences', [
                'preferences' => [
                    ['event_type' => 'password_changed', 'in_app' => false, 'email' => false],
                ],
            ])
            ->assertOk();

        Notification::fake();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'Passw0rd1!',
                'password' => 'NewPassw0rd1!',
                'password_confirmation' => 'NewPassw0rd1!',
            ])
            ->assertOk();

        Notification::assertSentTo(
            $user,
            PasswordChangedNotification::class,
            fn ($notification, array $channels) => in_array('database', $channels, true) && in_array('mail', $channels, true),
        );
    }

    public function test_mobile_only_account_never_gets_a_mail_channel_regardless_of_preference_state(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'mobile' => '+966500000123',
            'mobile_verified_at' => now(),
            'password' => 'Passw0rd1!',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // Explicitly (attempt to) enable email — still unreachable with no email on file.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/me/notification-preferences', [
                'preferences' => [
                    ['event_type' => 'new_device_login', 'in_app' => true, 'email' => true],
                ],
            ])
            ->assertOk();

        Notification::fake();

        $this->withHeader('User-Agent', 'test-agent-1')
            ->postJson('/api/v1/core/auth/login', [
                'login' => $user->mobile,
                'password' => 'Passw0rd1!',
                'device_name' => 'Device A',
            ])
            ->assertOk();

        Notification::assertSentTo(
            $user,
            NewDeviceLoginNotification::class,
            fn ($notification, array $channels) => in_array('database', $channels, true) && ! in_array('mail', $channels, true),
        );
    }
}
