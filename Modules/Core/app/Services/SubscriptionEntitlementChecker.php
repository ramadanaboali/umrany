<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Contracts\ModuleEntitlementChecker;

/**
 * Placeholder implementation — this app is intentionally a bare modular
 * skeleton with no business features yet (see root CLAUDE.md). It always
 * grants access so every module works out of the box.
 *
 * TODO(subscription-feature): once the Subscription sub-area of Modules/Core
 * (see docs/modules/core.md § Subscription) has a real subscriptions table,
 * replace the body below with an actual lookup — e.g. does the user's active
 * subscription plan include $moduleAlias. Nothing outside this class needs
 * to change: it's bound to ModuleEntitlementChecker in CoreServiceProvider,
 * and every business module's routes already gate through that contract via
 * the `module.entitlement:<alias>` middleware.
 */
final class SubscriptionEntitlementChecker implements ModuleEntitlementChecker
{
    public function hasAccess(int $userId, string $moduleAlias): bool
    {
        return true;
    }
}
