<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Services\ProviderService;
use Tests\TestCase;

class ProviderPortfolioTest extends TestCase
{
    /**
     * @return array{0: User, 1: string, 2: Provider}
     */
    private function providerWithToken(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['full_name' => $user->name]);
        $token = $user->createToken('test')->plainTextToken;

        $category = ProviderCategory::create(['slug' => 'contractor-'.uniqid(), 'name_en' => 'Contractor', 'name_ar' => 'مقاول']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$category->id]]);

        return [$user, $token, $user->provider()->first()];
    }

    /**
     * Activates a provider directly through the service (no HTTP round-trip) — used only by the
     * two-distinct-identities ownership tests below, so their setup never touches Sanctum's
     * `RequestGuard` (which caches the resolved user for the guard instance's lifetime — a second
     * `withHeader('Authorization', ...)`-driven activation for a *different* user within the same
     * test method would otherwise silently authenticate as the first user again, confirmed
     * directly by reproduction).
     *
     * @return array{0: User, 1: Provider}
     */
    private function activatedProvider(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['full_name' => $user->name]);
        $category = ProviderCategory::create(['slug' => 'contractor-'.uniqid(), 'name_en' => 'Contractor', 'name_ar' => 'مقاول']);

        $provider = app(ProviderService::class)->activate($user, [
            'company_name' => 'Acme',
            'category_ids' => [$category->id],
        ]);

        return [$user, $provider];
    }

    public function test_can_create_a_portfolio_item_with_images_and_video_urls(): void
    {
        Storage::fake('public');
        [, $token] = $this->providerWithToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/portfolio', [
                'title' => 'Villa Renovation',
                'description' => 'Full renovation project',
                'images' => [UploadedFile::fake()->image('project.jpg')],
                'videos' => ['https://youtube.com/watch?v=abc123'],
                'completion_date' => '2024-01-15',
                'project_location' => 'Riyadh',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Villa Renovation')
            ->assertJsonPath('data.videos.0', 'https://youtube.com/watch?v=abc123')
            ->assertJsonCount(1, 'data.image_urls');

        $this->assertDatabaseCount('provider_portfolios', 1);
    }

    public function test_can_update_a_portfolio_item(): void
    {
        Storage::fake('public');
        [, $token] = $this->providerWithToken();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/portfolio', ['title' => 'Original Title']);

        $portfolioId = $create->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/core/providers/me/portfolio/{$portfolioId}", ['title' => 'Updated Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_can_delete_a_portfolio_item(): void
    {
        [, $token] = $this->providerWithToken();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/portfolio', ['title' => 'To Delete']);

        $portfolioId = $create->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->delete("/api/v1/core/providers/me/portfolio/{$portfolioId}")
            ->assertStatus(204);

        $this->assertSoftDeleted('provider_portfolios', ['id' => $portfolioId]);
    }

    /**
     * Ownership check — a different provider's owner must never be able to mutate someone else's
     * portfolio item. Uses actingAs() rather than switching Bearer-token headers mid-test: Sanctum's
     * `RequestGuard` caches the resolved user for the guard instance's lifetime, so a second
     * `withHeader('Authorization', ...)` call with a *different* user's token within the same test
     * method would silently keep resolving to the first user (confirmed directly by reproduction) —
     * actingAs() bypasses the token-callback resolution entirely via Guard::setUser().
     */
    public function test_a_different_providers_owner_cannot_update_someone_elses_portfolio_item(): void
    {
        [$firstUser] = $this->activatedProvider();
        [$secondUser] = $this->activatedProvider();

        $create = $this->actingAs($firstUser, 'sanctum')
            ->post('/api/v1/core/providers/me/portfolio', ['title' => 'Owned By First']);

        $portfolioId = $create->json('data.id');

        $this->actingAs($secondUser, 'sanctum')
            ->postJson("/api/v1/core/providers/me/portfolio/{$portfolioId}", ['title' => 'Hijacked'])
            ->assertStatus(404);
    }

    public function test_a_different_providers_owner_cannot_delete_someone_elses_portfolio_item(): void
    {
        [$firstUser] = $this->activatedProvider();
        [$secondUser] = $this->activatedProvider();

        $create = $this->actingAs($firstUser, 'sanctum')
            ->post('/api/v1/core/providers/me/portfolio', ['title' => 'Owned By First']);

        $portfolioId = $create->json('data.id');

        $this->actingAs($secondUser, 'sanctum')
            ->delete("/api/v1/core/providers/me/portfolio/{$portfolioId}")
            ->assertStatus(404);

        $this->assertDatabaseHas('provider_portfolios', ['id' => $portfolioId, 'deleted_at' => null]);
    }
}
