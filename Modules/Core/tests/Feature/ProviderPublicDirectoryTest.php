<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\Country;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderCategory;
use Tests\TestCase;

class ProviderPublicDirectoryTest extends TestCase
{
    private function publishedApprovedProvider(string $companyName = 'Visible Co.'): Provider
    {
        $user = User::factory()->create();
        $provider = Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'status' => ProviderStatus::Published,
        ]);
        $provider->verification()->create(['status' => ProviderVerificationStatus::Approved]);

        return $provider;
    }

    public function test_public_directory_only_returns_published_and_approved_providers(): void
    {
        $visible = $this->publishedApprovedProvider('Visible Co.');

        $pendingUser = User::factory()->create();
        $pendingProvider = Provider::forceCreate([
            'user_id' => $pendingUser->id,
            'company_name' => 'Pending Co.',
            'status' => ProviderStatus::PendingReview,
        ]);
        $pendingProvider->verification()->create(['status' => ProviderVerificationStatus::Pending]);

        $suspendedUser = User::factory()->create();
        $suspendedProvider = Provider::forceCreate([
            'user_id' => $suspendedUser->id,
            'company_name' => 'Suspended Co.',
            'status' => ProviderStatus::Suspended,
        ]);
        $suspendedProvider->verification()->create(['status' => ProviderVerificationStatus::Approved]);

        $response = $this->getJson('/api/v1/core/providers')->assertOk();

        $names = $response->json('data.*.company_name');
        $this->assertContains('Visible Co.', $names);
        $this->assertNotContains('Pending Co.', $names);
        $this->assertNotContains('Suspended Co.', $names);
    }

    public function test_public_directory_is_unauthenticated(): void
    {
        $this->publishedApprovedProvider();

        $this->getJson('/api/v1/core/providers')->assertOk();
    }

    public function test_can_view_a_published_approved_provider_publicly(): void
    {
        $provider = $this->publishedApprovedProvider();

        $this->getJson("/api/v1/core/providers/{$provider->id}")
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Visible Co.');
    }

    public function test_a_pending_provider_404s_on_the_public_show_endpoint(): void
    {
        $user = User::factory()->create();
        $provider = Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => 'Pending Co.',
            'status' => ProviderStatus::PendingReview,
        ]);
        $provider->verification()->create(['status' => ProviderVerificationStatus::Pending]);

        $this->getJson("/api/v1/core/providers/{$provider->id}")->assertStatus(404);
    }

    public function test_a_suspended_provider_404s_on_the_public_show_endpoint(): void
    {
        $user = User::factory()->create();
        $provider = Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => 'Suspended Co.',
            'status' => ProviderStatus::Suspended,
        ]);
        $provider->verification()->create(['status' => ProviderVerificationStatus::Approved]);

        $this->getJson("/api/v1/core/providers/{$provider->id}")->assertStatus(404);
    }

    /**
     * Regression guard for the route-ordering fix: GET /providers/me must still resolve to
     * ProviderController::show(), never be shadowed by the public GET /providers/{provider}
     * wildcard route (which would otherwise try — and fail — to bind a Provider with id "me").
     */
    public function test_providers_me_is_not_shadowed_by_the_public_wildcard_route(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['full_name' => $user->name]);
        $token = $user->createToken('test')->plainTextToken;
        $category = ProviderCategory::create(['slug' => 'contractor-'.uniqid(), 'name_en' => 'Contractor', 'name_ar' => 'مقاول']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'My Own Co.', 'category_ids' => [$category->id]]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/providers/me')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'My Own Co.');
    }

    /**
     * Regression guard: filter[city_id]/filter[country_id] must be an exact match, not the
     * partial (LIKE '%value%') match Spatie's QueryBuilder defaults bare filter names to — a
     * provider with city_id=21 must never match filter[city_id]=1 just because "1" is a
     * substring of "21".
     */
    public function test_public_directory_filters_city_id_by_exact_match_not_substring(): void
    {
        $country = Country::forceCreate(['code' => 'sa', 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية', 'phone_code' => '966']);

        // Explicit ids (bypassing model mass-assignment guarding) chosen so the filter value
        // "1200" is a literal substring of the decoy's id "12003" — a partial (LIKE) match would
        // wrongly include the decoy; only an exact match correctly excludes it.
        DB::table('cities')->insert([
            ['id' => 1200, 'country_id' => $country->id, 'name_en' => 'City 1200', 'name_ar' => 'مدينة ١٢٠٠', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12003, 'country_id' => $country->id, 'name_en' => 'City 12003', 'name_ar' => 'مدينة ١٢٠٠٣', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $matching = $this->publishedApprovedProvider('City 1200 Co.');
        $matching->forceFill(['city_id' => 1200])->save();

        $substringMatch = $this->publishedApprovedProvider('City 12003 Co.');
        $substringMatch->forceFill(['city_id' => 12003])->save();

        $response = $this->getJson('/api/v1/core/providers?filter[city_id]=1200')->assertOk();

        $names = $response->json('data.*.company_name');
        $this->assertContains('City 1200 Co.', $names);
        $this->assertNotContains('City 12003 Co.', $names);
    }

    public function test_a_published_approved_provider_exposes_its_portfolios_and_certificates_publicly(): void
    {
        $provider = $this->publishedApprovedProvider();
        $provider->portfolios()->create(['title' => 'Public Portfolio Item']);
        $provider->certificates()->create(['name' => 'Public Certificate']);

        $response = $this->getJson("/api/v1/core/providers/{$provider->id}")->assertOk();

        $this->assertSame('Public Portfolio Item', $response->json('data.portfolios.0.title'));
        $this->assertSame('Public Certificate', $response->json('data.certificates.0.name'));
    }

    public function test_public_directory_can_filter_by_category(): void
    {
        $matching = $this->publishedApprovedProvider('Matching Co.');
        $nonMatching = $this->publishedApprovedProvider('Non-Matching Co.');

        $category = ProviderCategory::create(['slug' => 'contractor-'.uniqid(), 'name_en' => 'Contractor', 'name_ar' => 'مقاول']);
        $matching->categories()->sync([$category->id]);

        $response = $this->getJson("/api/v1/core/providers?filter[category]={$category->id}")->assertOk();

        $names = $response->json('data.*.company_name');
        $this->assertContains('Matching Co.', $names);
        $this->assertNotContains('Non-Matching Co.', $names);
    }
}
