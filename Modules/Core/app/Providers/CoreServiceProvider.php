<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Console\Commands\AuditPermissionsCommand;
use Modules\Core\Console\Commands\SyncPermissionsCommand;
use Modules\Core\Contracts\ModuleEntitlementChecker;
use Modules\Core\Contracts\UserCapabilityResolver;
use Modules\Core\Http\Middleware\EnsureAccountIsVerified;
use Modules\Core\Http\Middleware\EnsureModuleEntitlement;
use Modules\Core\Models\Admin;
use Modules\Core\Repositories\Contracts\AdminRepositoryInterface;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;
use Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserProfileRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Repositories\Contracts\VerificationCodeRepositoryInterface;
use Modules\Core\Repositories\EloquentAdminRepository;
use Modules\Core\Repositories\EloquentProviderRepository;
use Modules\Core\Repositories\EloquentRoleRepository;
use Modules\Core\Repositories\EloquentUserProfileRepository;
use Modules\Core\Repositories\EloquentUserRepository;
use Modules\Core\Repositories\EloquentVerificationCodeRepository;
use Modules\Core\Services\CapabilityService;
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
    protected array $commands = [
        SyncPermissionsCommand::class,
        AuditPermissionsCommand::class,
    ];

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
        $this->app->bind(UserCapabilityResolver::class, CapabilityService::class);

        // Repository bindings — see docs/architecture/backend-layering.md. Plain bind(), not
        // singleton(): none of these carry request-scoped state, but binding non-singleton is
        // the safe default under Octane's persistent workers (Rule 4, docs/architecture/infrastructure.md).
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, EloquentUserProfileRepository::class);
        $this->app->bind(VerificationCodeRepositoryInterface::class, EloquentVerificationCodeRepository::class);
        $this->app->bind(ProviderRepositoryInterface::class, EloquentProviderRepository::class);
        $this->app->bind(AdminRepositoryInterface::class, EloquentAdminRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Router::class)
            ->aliasMiddleware('module.entitlement', EnsureModuleEntitlement::class)
            ->aliasMiddleware('account.verified', EnsureAccountIsVerified::class);

        // Super Admin bypasses every permission check — keyed on the `is_super_admin` boolean
        // column, never on holding a role named "Super Admin" (role names are admin-created
        // examples per docs/business/personas.md, not fixed identities a security bypass should
        // depend on). Returning null (not false) for non-super-admins lets normal permission
        // checks still run instead of being short-circuited to deny.
        Gate::before(fn ($user, string $ability) => $user instanceof Admin && $user->is_super_admin ? true : null);
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
