<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\UserCapabilityResolver;
use Modules\Core\Data\UserCapabilities;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;

/**
 * TODO(subscription-feature): $hasEcommerceAccess/$hasErpAccess currently read
 * providers.has_ecommerce_access/has_erp_access directly, and $maxProjectOffers is a hardcoded
 * default — both are placeholders until Modules/Core's Subscription sub-area (roadmap Phase 2)
 * exists. Nothing outside this class needs to change when that lands — it's bound to
 * UserCapabilityResolver in CoreServiceProvider, same pattern as SubscriptionEntitlementChecker.
 *
 * Cache-aside over the default cache store — see docs/architecture/backend-layering.md §
 * Caching. Deliberately NOT Cache::store('redis') hardcoded: that would bypass phpunit.xml's
 * CACHE_STORE=array test override entirely, making every test that touches this class read/write
 * the real Redis instance instead of an isolated per-run store (discovered directly — an earlier,
 * differently-shaped cached DTO under a stale class definition caused a real
 * `__PHP_Incomplete_Class` TypeError under test). In actual dev/staging/production this still
 * resolves to Redis, because CACHE_STORE=redis in .env — the "use Redis for caching" outcome is
 * achieved by the environment's own default, not by hardcoding a store name in application code.
 * Invalidated by Modules\Core\Listeners\ForgetCachedUserCapabilities whenever
 * Modules\Core\Events\UserCapabilitiesChanged fires (Provider activation/update/verification
 * changes) — never trust this cache past a write without going through that event.
 */
final class CapabilityService implements UserCapabilityResolver
{
    private const DEFAULT_MAX_PROJECT_OFFERS = 3;

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly ProviderRepositoryInterface $providers,
    ) {}

    public function capabilitiesFor(int $userId): UserCapabilities
    {
        return Cache::remember(
            self::cacheKey($userId),
            self::CACHE_TTL_SECONDS,
            fn () => $this->resolve($userId),
        );
    }

    public function forgetFor(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }

    private function resolve(int $userId): UserCapabilities
    {
        $provider = $this->providers->findByUserId($userId);

        return new UserCapabilities(
            isProvider: $provider !== null,
            providerVerified: $provider?->isPubliclyVisible() ?? false,
            // Larastan infers $provider as non-nullable on the next two lines, which is wrong:
            // findByUserId() genuinely returns null for the very common case of a registered
            // user with no Provider row (confirmed by testing when this lived in the
            // pre-refactor CapabilityResolver — removing the nullsafe introduces no new Larastan
            // error, but does introduce a real "Attempt to read property on null" fatal for
            // exactly that case). Keep the nullsafe; the ignores below suppress a false positive.
            // @phpstan-ignore nullsafe.neverNull
            hasEcommerceAccess: $provider?->has_ecommerce_access ?? false,
            // @phpstan-ignore nullsafe.neverNull
            hasErpAccess: $provider?->has_erp_access ?? false,
            maxProjectOffers: self::DEFAULT_MAX_PROJECT_OFFERS,
        );
    }

    private static function cacheKey(int $userId): string
    {
        return "umrany:core:capabilities:{$userId}";
    }
}
