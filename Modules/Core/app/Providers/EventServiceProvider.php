<?php

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Events\UserCapabilitiesChanged;
use Modules\Core\Listeners\ForgetCachedUserCapabilities;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * Registered explicitly, not via auto-discovery: Laravel's event auto-discovery
     * (`shouldDiscoverEvents()`) only ever activates for the base
     * `Illuminate\Foundation\Support\Providers\EventServiceProvider` class itself (it checks
     * `get_class($this) === __CLASS__`), never for a subclass like this one — so
     * `$shouldDiscoverEvents = true` below is inert for a module provider. Don't rely on
     * auto-discovery for any Core listener; add it here.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserCapabilitiesChanged::class => [
            ForgetCachedUserCapabilities::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
