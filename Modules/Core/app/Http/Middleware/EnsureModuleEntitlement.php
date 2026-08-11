<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ModuleEntitlementChecker;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates an entire module's routes behind subscription/entitlement, without
 * that module knowing anything about how entitlement is decided — see
 * Modules\Core\Contracts\ModuleEntitlementChecker.
 *
 * Usage (in a business module's routes/api.php):
 *   Route::middleware(['auth:sanctum', 'module.entitlement:ecommerce'])->group(...)
 */
final class EnsureModuleEntitlement
{
    public function __construct(
        private readonly ModuleEntitlementChecker $entitlement,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleAlias): Response
    {
        $user = $request->user();

        if (! $user || ! $this->entitlement->hasAccess((int) $user->getAuthIdentifier(), $moduleAlias)) {
            abort(402, "Your account does not have access to the \"{$moduleAlias}\" module.");
        }

        return $next($request);
    }
}
