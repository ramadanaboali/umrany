<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Notifications\NewDeviceLoginNotification;
use Modules\Core\Notifications\PasswordChangedNotification;
use Modules\Core\Notifications\RegistrationCompletedNotification;
use Tests\TestCase;

class NotificationListTest extends TestCase
{
    public function test_index_returns_envelope_shape_with_unread_first_ordering(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Notification::fake() is deliberately NOT active here — these actually persist to the
        // notifications table via the real "database" channel.
        $user->notify(new RegistrationCompletedNotification('en'));
        $user->notify(new PasswordChangedNotification('en'));
        $user->notify(new NewDeviceLoginNotification('My Phone', '127.0.0.1', Carbon::now(), 'en'));

        // Mark one specific row read by type rather than by creation-order querying — all three
        // notifications land in the same second, so relying on latest()/oldest() to disambiguate
        // rows with identical timestamps would be flaky.
        $user->notifications()->where('type', RegistrationCompletedNotification::class)->firstOrFail()->markAsRead();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/notifications')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'type', 'data', 'read_at', 'created_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        $data = $response->json('data');
        $meta = $response->json('meta');

        $this->assertSame(3, $meta['total']);
        $this->assertSame(1, $meta['current_page']);
        $this->assertSame(1, $meta['last_page']);

        // Unread rows first.
        $this->assertNull($data[0]['read_at']);
        $this->assertNull($data[1]['read_at']);
        $this->assertNotNull($data[2]['read_at']);
    }

    public function test_mark_one_notification_read_sets_only_its_own_read_at(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $user->notify(new RegistrationCompletedNotification('en'));
        $user->notify(new PasswordChangedNotification('en'));

        // Select each row by its notification type rather than by creation-order querying — both
        // rows can land in the same second, so first()/last() on created_at order alone would be
        // flaky about which is "the target" vs "the untouched one".
        $target = $user->notifications()->where('type', RegistrationCompletedNotification::class)->firstOrFail();
        $untouched = $user->notifications()->where('type', PasswordChangedNotification::class)->firstOrFail();

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/core/me/notifications/{$target->id}/read")
            ->assertStatus(204);

        $this->assertNotNull($target->refresh()->read_at);
        $this->assertNull($untouched->refresh()->read_at);
    }

    public function test_mark_all_read_sets_read_at_on_every_unread_row(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $user->notify(new RegistrationCompletedNotification('en'));
        $user->notify(new PasswordChangedNotification('en'));
        $user->notify(new NewDeviceLoginNotification('My Phone', '127.0.0.1', Carbon::now(), 'en'));

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/me/notifications/read-all')
            ->assertStatus(204);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(3, $user->notifications()->whereNotNull('read_at')->count());
    }

    public function test_count_endpoint_reports_total_and_unread(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $user->notify(new RegistrationCompletedNotification('en'));
        $user->notify(new PasswordChangedNotification('en'));
        $user->notifications()->where('type', RegistrationCompletedNotification::class)->firstOrFail()->markAsRead();

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/notifications/count')
            ->assertOk()
            ->assertJson(['data' => ['total' => 2, 'unread' => 1]]);
    }

    public function test_filter_by_read_returns_only_unread_rows(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $user->notify(new RegistrationCompletedNotification('en'));
        $user->notify(new PasswordChangedNotification('en'));
        $user->notifications()->where('type', RegistrationCompletedNotification::class)->firstOrFail()->markAsRead();

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/notifications?filter[read]=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'PasswordChangedNotification');
    }

    public function test_filter_by_type_returns_only_matching_notification_class(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $user->notify(new RegistrationCompletedNotification('en'));
        $user->notify(new PasswordChangedNotification('en'));

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/notifications?filter[type]=PasswordChanged')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'PasswordChangedNotification');
    }
}
