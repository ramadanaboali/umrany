<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Database\Seeders\ProviderCategorySeeder;
use Tests\TestCase;

class ProviderCategoryTest extends TestCase
{
    public function test_provider_categories_endpoint_returns_the_ten_seeded_categories(): void
    {
        $this->seed(ProviderCategorySeeder::class);

        $response = $this->getJson('/api/v1/core/provider-categories')
            ->assertOk();

        $response->assertJsonCount(10, 'data');
        $this->assertSame('contractor', $response->json('data.0.slug'));
        $this->assertNotEmpty($response->json('data.0.name_ar'));
    }

    public function test_provider_categories_endpoint_is_public(): void
    {
        $this->seed(ProviderCategorySeeder::class);

        $this->getJson('/api/v1/core/provider-categories')->assertOk();
    }

    public function test_provider_categories_are_ordered_by_display_order(): void
    {
        $this->seed(ProviderCategorySeeder::class);

        $slugs = $this->getJson('/api/v1/core/provider-categories')->json('data.*.slug');

        $this->assertSame('contractor', $slugs[0]);
        $this->assertSame('steel-structure', $slugs[9]);
    }

    public function test_provider_categories_endpoint_supports_search_filter(): void
    {
        $this->seed(ProviderCategorySeeder::class);

        $response = $this->getJson('/api/v1/core/provider-categories?filter[search]=مقاول')->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertSame('contractor', $response->json('data.0.slug'));
    }
}
