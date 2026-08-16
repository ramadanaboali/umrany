<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Tests\TestCase;

/**
 * Proves the `core.admin.{id}` private channel (Modules\Core\Events\AdminPermissionsChanged) is
 * resolved against the `admin` guard specifically — see routes/channels.php and
 * docs/decisions/0008-admin-rbac-live-refresh-via-reverb.md.
 */
class AdminChannelAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The test suite forces BROADCAST_CONNECTION=null (phpunit.xml) for speed, but
        // NullBroadcaster::auth() is a no-op that never invokes the channel's authorization
        // callback at all — every request would trivially "pass" regardless of the actual
        // core.admin.{id} closure in routes/channels.php. Switch to the real `reverb` connection
        // (Pusher-protocol-compatible; signing is local, no network call) so these tests exercise
        // the real Broadcaster::auth() -> retrieveUser()/channel-callback path.
        config(['broadcasting.default' => 'reverb']);

        // routes/channels.php already ran once during application boot, while the connection was
        // still `null` — Broadcast::channel() registers each pattern onto whichever broadcaster
        // instance is current *at call time*, so those registrations landed on the `null`
        // broadcaster, not `reverb`. Re-requiring the file now (after switching the connection
        // above) re-registers every channel onto the `reverb` broadcaster this test actually
        // exercises. In the real app this non-issue never arises: BROADCAST_CONNECTION is set
        // once, correctly, before boot (see .env), so channels register onto the right connection
        // the first and only time.
        require base_path('routes/channels.php');
    }

    private function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
    }

    public function test_admin_can_authenticate_their_own_channel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post('/broadcasting/auth', [
                'channel_name' => "private-core.admin.{$admin->id}",
                'socket_id' => '1234.1234',
            ])
            ->assertOk();
    }

    public function test_admin_cannot_authenticate_another_admins_channel(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post('/broadcasting/auth', [
                'channel_name' => "private-core.admin.{$other->id}",
                'socket_id' => '1234.1234',
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_request_cannot_authenticate_any_admin_channel(): void
    {
        $admin = $this->admin();

        // Laravel's PusherBroadcaster::auth() doesn't distinguish "no user at all" from "wrong
        // user for this channel" — both throw the same AccessDeniedHttpException (403), never 401.
        $this->post('/broadcasting/auth', [
            'channel_name' => "private-core.admin.{$admin->id}",
            'socket_id' => '1234.1234',
        ])->assertForbidden();
    }
}
