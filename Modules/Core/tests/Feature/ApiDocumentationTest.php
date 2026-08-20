<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Tests\TestCase;

/**
 * Guards the Scribe → Scramble migration (docs/decisions/0023-scramble-over-scribe.md) — in
 * particular that the admin dashboard stays excluded from the API spec, and that
 * `MiddlewareAuthSecurityStrategy` correctly replaced the 24 hand-written `@authenticated` tags.
 */
class ApiDocumentationTest extends TestCase
{
    public function test_openapi_document_is_reachable_and_valid(): void
    {
        $response = $this->getJson('/docs/openapi.json')->assertOk();

        $this->assertSame('3.1.0', $response->json('openapi'));
    }

    public function test_every_modules_representative_endpoint_is_documented(): void
    {
        $paths = $this->getJson('/docs/openapi.json')->json('paths');

        foreach ([
            '/v1/core/auth/login',
            '/v1/core/profile',
            '/v1/core/providers/me',
            '/v1/core/me/notifications',
            '/v1/core/countries',
            '/v1/core/settings',
        ] as $path) {
            $this->assertArrayHasKey($path, $paths, "Missing path: {$path}");
        }
    }

    public function test_admin_dashboard_routes_are_excluded(): void
    {
        $paths = $this->getJson('/docs/openapi.json')->json('paths');

        foreach (array_keys($paths) as $path) {
            $this->assertStringStartsNotWith('/admin', $path);
        }
    }

    public function test_bearer_security_scheme_is_registered(): void
    {
        $schemes = $this->getJson('/docs/openapi.json')->json('components.securitySchemes');

        $this->assertNotEmpty(
            array_filter($schemes, fn (array $scheme) => $scheme['type'] === 'http' && $scheme['scheme'] === 'bearer'),
        );
    }

    public function test_public_routes_have_no_security_requirement_while_authenticated_ones_do(): void
    {
        $paths = $this->getJson('/docs/openapi.json')->json('paths');

        $this->assertSame([], $paths['/v1/core/auth/login']['post']['security'] ?? null);
        $this->assertArrayNotHasKey('security', $paths['/v1/core/profile']['get'] ?? []);
    }
}
