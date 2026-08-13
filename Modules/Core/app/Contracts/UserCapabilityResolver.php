<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Data\UserCapabilities;

/**
 * Resolves a user's current capability set — Project Owner / Supplier / ERP User are never
 * stored roles (see docs/business/personas.md), they're computed from Provider identity +
 * verification + subscription state every time this is called. Sibling contract to
 * ModuleEntitlementChecker: that one answers "may this request proceed" (security-critical,
 * per-module boolean); this one answers "what should the client render" (UI-hint, full shape).
 */
interface UserCapabilityResolver
{
    public function capabilitiesFor(int $userId): UserCapabilities;
}
