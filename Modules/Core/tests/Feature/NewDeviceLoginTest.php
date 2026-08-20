<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Notifications\NewDeviceLoginNotification;
use Modules\Core\Notifications\SuspiciousLoginAttemptNotification;
use Tests\TestCase;

class NewDeviceLoginTest extends TestCase
{
    public function test_first_login_from_a_device_fires_new_device_login_notification_and_creates_a_device_row(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);

        $this->withHeader('User-Agent', 'test-agent-1')
            ->postJson('/api/v1/core/auth/login', [
                'login' => $user->email,
                'password' => 'Passw0rd1!',
                'device_name' => 'My Phone',
            ])
            ->assertOk();

        Notification::assertSentToTimes($user, NewDeviceLoginNotification::class, 1);
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'fingerprint' => hash('sha256', 'test-agent-1|My Phone'),
        ]);
    }

    public function test_second_login_from_the_same_device_does_not_refire_the_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);

        $login = fn () => $this->withHeader('User-Agent', 'test-agent-1')
            ->postJson('/api/v1/core/auth/login', [
                'login' => $user->email,
                'password' => 'Passw0rd1!',
                'device_name' => 'My Phone',
            ])
            ->assertOk();

        $login();
        $login();

        Notification::assertSentToTimes($user, NewDeviceLoginNotification::class, 1);
        $this->assertDatabaseCount('user_devices', 1);
    }

    public function test_login_from_a_different_device_fires_the_notification_again(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);

        $this->withHeader('User-Agent', 'test-agent-1')
            ->postJson('/api/v1/core/auth/login', [
                'login' => $user->email,
                'password' => 'Passw0rd1!',
                'device_name' => 'My Phone',
            ])
            ->assertOk();

        $this->withHeader('User-Agent', 'test-agent-2')
            ->postJson('/api/v1/core/auth/login', [
                'login' => $user->email,
                'password' => 'Passw0rd1!',
                'device_name' => 'My Laptop',
            ])
            ->assertOk();

        Notification::assertSentToTimes($user, NewDeviceLoginNotification::class, 2);
        $this->assertDatabaseCount('user_devices', 2);
    }

    public function test_suspicious_login_attempt_notification_fires_once_after_crossing_threshold(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);

        $threshold = (int) config('core.security.suspicious_login.threshold');

        // Two more attempts than the threshold, each from a different IP so the per-(login, ip)
        // "throttle:login" rate limiter (5/min) never interferes with exercising the *separate*,
        // user-id-keyed suspicious-login counter this test targets.
        for ($i = 1; $i <= $threshold + 2; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->postJson('/api/v1/core/auth/login', [
                    'login' => $user->email,
                    'password' => 'WrongPassword!',
                ])
                ->assertStatus(422);
        }

        // Notified exactly once for crossing the threshold, not once per attempt afterwards —
        // the ":notified" cache flag suppresses repeats for the rest of the window.
        Notification::assertSentToTimes($user, SuspiciousLoginAttemptNotification::class, 1);
    }
}
