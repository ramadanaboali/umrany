<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Models\City;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Tests\TestCase;

/**
 * Covers the ?filter[search]= addition to the public master-data pickers — deliberately still
 * unpaginated (CLAUDE.md Rule 10's named exception), filters only. See docs/decisions/0029-
 * centralized-notification-service-and-api-locale.md.
 */
class MasterDataFilterTest extends TestCase
{
    public function test_countries_search_filters_by_partial_english_or_arabic_name(): void
    {
        Country::create(['code' => 'SA', 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية', 'is_active' => true]);
        Country::create(['code' => 'EG', 'name_en' => 'Egypt', 'name_ar' => 'مصر', 'is_active' => true]);

        $response = $this->getJson('/api/v1/core/countries?filter[search]=saudi')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('SA', $response->json('data.0.code'));
    }

    public function test_countries_listing_has_no_pagination_envelope(): void
    {
        Country::create(['code' => 'SA', 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية', 'is_active' => true]);

        $this->getJson('/api/v1/core/countries')
            ->assertOk()
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links');
    }

    public function test_countries_search_excludes_inactive_rows_server_side(): void
    {
        Country::create(['code' => 'XX', 'name_en' => 'Hidden Land', 'name_ar' => 'مخفي', 'is_active' => false]);

        $response = $this->getJson('/api/v1/core/countries?filter[search]=hidden')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_cities_search_filters_within_the_given_country(): void
    {
        $country = Country::create(['code' => 'SA', 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية', 'is_active' => true]);
        City::create(['country_id' => $country->id, 'name_en' => 'Riyadh', 'name_ar' => 'الرياض', 'is_active' => true]);
        City::create(['country_id' => $country->id, 'name_en' => 'Jeddah', 'name_ar' => 'جدة', 'is_active' => true]);

        $response = $this->getJson("/api/v1/core/countries/{$country->id}/cities?filter[search]=riy")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Riyadh', $response->json('data.0.name_en'));
    }

    public function test_currencies_search_filters_by_partial_name(): void
    {
        Currency::create(['code' => 'SAR', 'name_en' => 'Saudi Riyal', 'name_ar' => 'ريال سعودي', 'symbol' => 'SAR', 'is_active' => true]);
        Currency::create(['code' => 'USD', 'name_en' => 'US Dollar', 'name_ar' => 'دولار', 'symbol' => '$', 'is_active' => true]);

        $response = $this->getJson('/api/v1/core/currencies?filter[search]=riyal')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('SAR', $response->json('data.0.code'));
    }
}
