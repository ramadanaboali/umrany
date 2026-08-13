<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Events\UserCapabilitiesChanged;
use Modules\Core\Services\CapabilityService;

/**
 * Intra-module wiring — depends on the concrete CapabilityService (not just the
 * UserCapabilityResolver read contract) because cache invalidation is an implementation detail
 * of that specific Service, not part of the public "resolve capabilities" contract. This is fine
 * within a single module; it would not be fine for a business module reaching into Core this way
 * (see docs/architecture/module-boundaries.md).
 */
final class ForgetCachedUserCapabilities implements ShouldQueue
{
    public function __construct(
        private readonly CapabilityService $capabilities,
    ) {}

    public function handle(UserCapabilitiesChanged $event): void
    {
        $this->capabilities->forgetFor($event->userId);
    }

    /**
     * Queued listeners are wired differently from Jobs/Notifications — Laravel's event
     * dispatcher reads a `viaQueue()` method here, not a `$queue` property (that property is
     * silently ignored for listeners; see Illuminate\Events\Dispatcher::queueHandler()).
     */
    public function viaQueue(): string
    {
        return 'core-default';
    }
}
