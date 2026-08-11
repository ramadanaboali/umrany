<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * The single seam that lets a business module (Projects/ECommerce/ERP/AI) be
 * bought and used independently of the others. A module's routes never check
 * "is the user subscribed" themselves — they gate through this contract via
 * the `module.entitlement:<alias>` middleware, so the actual subscription
 * logic can live entirely in Core and change without touching other modules.
 */
interface ModuleEntitlementChecker
{
    /**
     * Whether the given user currently has access to the given module.
     *
     * @param  string  $moduleAlias  lowercase module alias, e.g. "ecommerce", "erp", "ai", "projects"
     */
    public function hasAccess(int $userId, string $moduleAlias): bool;
}
