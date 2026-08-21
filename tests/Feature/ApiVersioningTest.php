<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The standing enforcement `docs/api/conventions.md`'s "Versioning and routing" section describes
 * but doesn't itself guarantee — every module's RouteServiceProvider only ever applies `/api` +
 * the `api` middleware group; the `v1/<alias>` segment is added by each module's own
 * routes/api.php purely by convention, with nothing structurally requiring it. This test is that
 * missing guardrail: any route ever registered in the `api` middleware group that isn't under
 * `api/v1/` (or a future `api/v2/`, etc.) fails this test immediately, instead of shipping
 * silently unversioned.
 */
class ApiVersioningTest extends TestCase
{
    public function test_every_route_in_the_api_middleware_group_is_versioned(): void
    {
        $unversioned = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('api', $route->gatherMiddleware(), true))
            ->reject(fn ($route) => (bool) preg_match('#^api/v\d+/#', $route->uri()))
            ->map(fn ($route) => $route->methods()[0].' /'.$route->uri())
            ->values();

        $this->assertTrue(
            $unversioned->isEmpty(),
            "Found unversioned route(s) in the 'api' middleware group: {$unversioned->implode(', ')}",
        );
    }
}
