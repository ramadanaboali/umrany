<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\SiteSetting;
use Tests\TestCase;

/**
 * Public, unauthenticated — see Modules\Core\Http\Controllers\SiteSettingController.
 */
class SiteSettingApiTest extends TestCase
{
    public function test_endpoint_is_reachable_without_authentication(): void
    {
        $this->getJson('/api/v1/core/settings')->assertOk();
    }

    public function test_returns_the_current_settings_shape(): void
    {
        SiteSetting::current()->update([
            'site_name' => 'Umrany',
            'contact_email' => 'hello@umrany.test',
            'social_links' => ['facebook' => 'https://facebook.com/umrany'],
        ]);

        $this->getJson('/api/v1/core/settings')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'site_name' => 'Umrany',
                    'contact_email' => 'hello@umrany.test',
                    'social_links' => ['facebook' => 'https://facebook.com/umrany'],
                    'logo_url' => null,
                ],
            ]);
    }

    public function test_logo_path_resolves_to_a_full_url(): void
    {
        Storage::fake('public');
        SiteSetting::current()->update(['logo_path' => 'site-settings/logo/logo.png']);

        $response = $this->getJson('/api/v1/core/settings')->assertOk();

        $this->assertNotNull($response->json('data.logo_url'));
        $this->assertStringContainsString('site-settings/logo/logo.png', $response->json('data.logo_url'));
    }
}
