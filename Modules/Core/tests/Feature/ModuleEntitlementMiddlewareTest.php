<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Proves the module.entitlement gate (docs/architecture/module-boundaries.md
 * § Independent purchasability) actually works end to end: middleware alias
 * resolves, the ModuleEntitlementChecker binding resolves, and both the
 * "no auth" and "authenticated" paths behave correctly. Registers a throwaway
 * route rather than depending on a real business-module endpoint, since none
 * exist yet in this skeleton — every real Projects/ECommerce/ERP/AI route
 * group already applies this same middleware (see each module's routes/api.php).
 */
class ModuleEntitlementMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'module.entitlement:demo'])
            ->get('/api/__test/entitlement-gated', fn () => response()->json(['ok' => true]));
    }

    public function test_guest_is_rejected_before_entitlement_is_even_checked(): void
    {
        $this->getJson('/api/__test/entitlement-gated')->assertStatus(401);
    }

    public function test_authenticated_user_passes_the_entitlement_gate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/__test/entitlement-gated')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }
}
