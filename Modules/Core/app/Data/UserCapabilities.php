<?php

declare(strict_types=1);

namespace Modules\Core\Data;

use Spatie\LaravelData\Data;

/**
 * The computed "which hats does this account currently wear" read model — never a stored role.
 * Returned by Modules\Core\Contracts\UserCapabilityResolver and exposed at
 * GET /api/v1/core/me/capabilities. Every field here is advisory/UI-hint only: no endpoint may
 * treat a client-supplied copy of this DTO as authoritative — actual gating always re-derives
 * from Modules\Core\Contracts\ModuleEntitlementChecker and the Provider/subscription tables at
 * request time. See docs/architecture/module-boundaries.md § User capability resolution.
 */
final class UserCapabilities extends Data
{
    public function __construct(
        public readonly bool $isProvider,
        public readonly bool $providerVerified,
        public readonly bool $hasEcommerceAccess,
        public readonly bool $hasErpAccess,
        public readonly int $maxProjectOffers,
        // Trivially derived (active + verified), not a stored per-user flag — there is no real
        // Projects-module concept to key a stored flag off yet. See Modules\Core\Enums\AccountType.
        public readonly bool $isProjectOwner,
        /** @var array<int, string> */
        public readonly array $accountTypes,
    ) {}
}
