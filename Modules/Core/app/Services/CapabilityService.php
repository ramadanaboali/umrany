<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\UserCapabilityResolver;
use Modules\Core\Data\UserCapabilities;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;

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
 *
 * Cache key is versioned (`:v3:`) — bump it again whenever either (a) the DTO's constructor shape
 * changes, or (b) the cached *representation* changes (object vs. array, property renames in the
 * array form), so an already-cached entry in the old shape/format is never handed to code that
 * expects the new one. History: `:v1:` → `:v2:` was the constructor-shape bump (added
 * isProjectOwner/accountTypes). `:v2:` → `:v3:` is this class no longer caching the `Data` object
 * directly (see below) — reusing `:v2:` would have handed old raw-object entries to code that now
 * expects `Cache::remember()` to return an array, a live `__PHP_Incomplete_Class`-adjacent
 * `TypeError` on the very entries this change was meant to fix.
 *
 * The cached value itself is the DTO's plain `toArray()` form, reconstituted via
 * `UserCapabilities::from()` on read — **not** the `Data` object cached directly. Caching a
 * `Spatie\LaravelData\Data` object via PHP's native (un)serialize (what `Cache::remember()` did
 * here previously) is fragile independent of the version-key discipline above: a live
 * `__PHP_Incomplete_Class` was hit in real (non-test) use with a cached entry whose properties
 * matched the *current* DTO shape exactly, so it wasn't a stale-shape problem — `Data` subclasses
 * just aren't reliably safe to round-trip through native object serialization. Caching the array
 * form sidesteps that class of failure entirely.
 */
final class CapabilityService implements UserCapabilityResolver
{
    private const DEFAULT_MAX_PROJECT_OFFERS = 3;

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly ProviderRepositoryInterface $providers,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function capabilitiesFor(int $userId): UserCapabilities
    {
        $cached = Cache::remember(
            self::cacheKey($userId),
            self::CACHE_TTL_SECONDS,
            fn () => $this->resolve($userId)->toArray(),
        );

        return UserCapabilities::from($cached);
    }

    public function forgetFor(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }

    private function resolve(int $userId): UserCapabilities
    {
        $provider = $this->providers->findByUserId($userId);
        $user = $this->users->findById($userId);

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
            isProjectOwner: $user !== null && $user->status->canAuthenticate() && $user->hasVerifiedIdentity(),
            // Same false positive as the $provider nullsafes above: the preceding line's
            // `$user !== null &&` makes Larastan wrongly infer $user as non-nullable here too.
            // findById() genuinely returns null for a deleted/missing user.
            // @phpstan-ignore nullsafe.neverNull
            accountTypes: $user?->account_types ?? [],
        );
    }

    private static function cacheKey(int $userId): string
    {
        return "umrany:core:capabilities:v3:{$userId}";
    }
}
