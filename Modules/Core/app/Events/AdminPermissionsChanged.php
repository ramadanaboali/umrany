<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever an admin's effective permissions may have changed while they could already be
 * logged in: their role assignments/status change (AdminManagementService::update()), a role they
 * hold gets its permissions synced (RoleManagementService::update()), or their Super Admin status
 * changes (PromoteSuperAdminCommand). Mirrors Modules\Core\Events\UserCapabilitiesChanged's shape
 * exactly, on the `admin` guard instead — see docs/decisions/0008-admin-rbac-live-refresh-via-
 * reverb.md. No listener is registered for this event: unlike UserCapabilitiesChanged (which also
 * invalidates a Redis capability cache), the admin guard's permission cache is already flushed
 * explicitly at every call site (`PermissionRegistrar::forgetCachedPermissions()`), so this event's
 * only job is the broadcast itself.
 */
final class AdminPermissionsChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Non-blocking — nothing waits on this being delivered promptly. */
    public string $broadcastQueue = 'core-default';

    public function __construct(
        public readonly int $adminId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("core.admin.{$this->adminId}")];
    }

    public function broadcastAs(): string
    {
        return 'permissions.changed';
    }
}
