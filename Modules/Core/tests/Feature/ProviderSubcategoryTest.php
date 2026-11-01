<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Modules\Core\Database\Seeders\ProviderCategorySeeder;
use Modules\Core\Database\Seeders\ProviderSubcategorySeeder;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Models\ProviderSubcategory;
use Tests\TestCase;

/**
 * FR-PROVIDER-003's subcategory layer — see docs/decisions/0032-provider-subcategories.md.
 * Subcategory selection is always optional, unlike categories.
 */
class ProviderSubcategoryTest extends TestCase
{
    private function seedTaxonomy(): void
    {
        $this->seed(ProviderCategorySeeder::class);
        $this->seed(ProviderSubcategorySeeder::class);
    }

    private function verifiedUser(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['full_name' => $user->name]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    public function test_provider_subcategories_endpoint_returns_seeded_subcategories(): void
    {
        $this->seedTaxonomy();

        $response = $this->getJson('/api/v1/core/provider-subcategories')->assertOk();

        $this->assertSame(34, count($response->json('data')));
    }

    public function test_provider_subcategories_endpoint_filters_by_category(): void
    {
        $this->seedTaxonomy();
        $categoryId = ProviderCategory::query()->where('slug', 'contractor')->value('id');

        $response = $this->getJson("/api/v1/core/provider-subcategories?filter[provider_category_id]={$categoryId}")->assertOk();

        $this->assertSame(4, count($response->json('data')));
        $this->assertSame('general-contracting', $response->json('data.0.slug'));
    }

    public function test_activation_accepts_optional_subcategory_ids(): void
    {
        $this->seedTaxonomy();
        [, $token] = $this->verifiedUser();

        $categoryId = ProviderCategory::query()->where('slug', 'contractor')->value('id');
        $subcategoryId = ProviderSubcategory::query()->where('slug', 'general-contracting')->value('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', [
                'company_name' => 'Acme',
                'category_ids' => [$categoryId],
                'subcategory_ids' => [$subcategoryId],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.subcategories.0.slug', 'general-contracting');
    }

    public function test_activation_succeeds_with_no_subcategories_at_all(): void
    {
        $this->seedTaxonomy();
        [, $token] = $this->verifiedUser();
        $categoryId = ProviderCategory::query()->where('slug', 'contractor')->value('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$categoryId]])
            ->assertStatus(201)
            ->assertJsonCount(0, 'data.subcategories');
    }

    public function test_a_subcategory_must_belong_to_a_selected_category(): void
    {
        $this->seedTaxonomy();
        [, $token] = $this->verifiedUser();

        $contractorCategoryId = ProviderCategory::query()->where('slug', 'contractor')->value('id');
        // "Home Automation" belongs to Smart Home, not Contractor — selecting it without also
        // selecting Smart Home must fail.
        $mismatchedSubcategoryId = ProviderSubcategory::query()->where('slug', 'home-automation')->value('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', [
                'company_name' => 'Acme',
                'category_ids' => [$contractorCategoryId],
                'subcategory_ids' => [$mismatchedSubcategoryId],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subcategory_ids');
    }

    public function test_can_update_subcategories_without_resending_category_ids(): void
    {
        $this->seedTaxonomy();
        [$user, $token] = $this->verifiedUser();

        $contractorCategoryId = ProviderCategory::query()->where('slug', 'contractor')->value('id');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$contractorCategoryId]]);

        $subcategoryId = ProviderSubcategory::query()->where('slug', 'residential-construction')->value('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/providers/me', ['subcategory_ids' => [$subcategoryId]])
            ->assertOk()
            ->assertJsonPath('data.subcategories.0.slug', 'residential-construction');
    }
}
