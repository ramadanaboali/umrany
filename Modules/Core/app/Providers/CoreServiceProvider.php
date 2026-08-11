<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Modules\Core\Contracts\ModuleEntitlementChecker;
use Modules\Core\Http\Middleware\EnsureModuleEntitlement;
use Modules\Core\Services\SubscriptionEntitlementChecker;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Core';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'core';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Bind the contracts every other module depends on. This is the ONE
     * place a business module's independent-purchasability is decided —
     * see Modules\Core\Contracts\ModuleEntitlementChecker.
     */
    public function register(): void
    {
        parent::register();

        $this->app->bind(ModuleEntitlementChecker::class, SubscriptionEntitlementChecker::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Router::class)
            ->aliasMiddleware('module.entitlement', EnsureModuleEntitlement::class);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
