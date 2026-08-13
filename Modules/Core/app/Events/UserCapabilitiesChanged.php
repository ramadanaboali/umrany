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
 * Fired whenever a user's computed capability set (Modules\Core\Data\UserCapabilities) may have
 * changed — Provider activation, Provider update, verification-status changes. Two listeners
 * react to it: ForgetCachedUserCapabilities (invalidates the Redis cache CapabilityService reads
 * from) and the broadcast itself (lets a connected client refresh live instead of polling
 * GET /api/v1/core/me/capabilities). See docs/architecture/module-boundaries.md § User capability
 * resolution.
 */
final class UserCapabilitiesChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Non-blocking — nothing waits on this being delivered promptly. */
    public string $broadcastQueue = 'core-default';

    public function __construct(
        public readonly int $userId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("core.user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'capabilities.changed';
    }
}
