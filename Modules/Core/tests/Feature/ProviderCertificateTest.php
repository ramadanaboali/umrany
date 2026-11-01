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

class ProviderCertificateTest extends TestCase
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

    public function test_can_create_a_certificate_with_a_file(): void
    {
        Storage::fake('public');
        [, $token] = $this->providerWithToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/certificates', [
                'name' => 'ISO 9001',
                'issuer' => 'ISO',
                'issued_date' => '2023-05-01',
                'file' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'ISO 9001')
            ->assertJsonPath('data.file_url', fn ($url) => $url !== null);

        $this->assertDatabaseCount('provider_certificates', 1);
    }

    public function test_can_update_a_certificate(): void
    {
        [, $token] = $this->providerWithToken();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/certificates', ['name' => 'Original Name']);

        $certificateId = $create->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/core/providers/me/certificates/{$certificateId}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_can_delete_a_certificate(): void
    {
        [, $token] = $this->providerWithToken();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/certificates', ['name' => 'To Delete']);

        $certificateId = $create->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->delete("/api/v1/core/providers/me/certificates/{$certificateId}")
            ->assertStatus(204);

        $this->assertSoftDeleted('provider_certificates', ['id' => $certificateId]);
    }

    /**
     * Ownership check — a different provider's owner must never be able to mutate someone else's
     * certificate. Uses actingAs() rather than switching Bearer-token headers mid-test: Sanctum's
     * `RequestGuard` caches the resolved user for the guard instance's lifetime, so a second
     * `withHeader('Authorization', ...)` call with a *different* user's token within the same test
     * method would silently keep resolving to the first user (confirmed directly by reproduction) —
     * actingAs() bypasses the token-callback resolution entirely via Guard::setUser().
     */
    public function test_a_different_providers_owner_cannot_update_someone_elses_certificate(): void
    {
        [$firstUser] = $this->activatedProvider();
        [$secondUser] = $this->activatedProvider();

        $create = $this->actingAs($firstUser, 'sanctum')
            ->post('/api/v1/core/providers/me/certificates', ['name' => 'Owned By First']);

        $certificateId = $create->json('data.id');

        $this->actingAs($secondUser, 'sanctum')
            ->postJson("/api/v1/core/providers/me/certificates/{$certificateId}", ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    public function test_a_different_providers_owner_cannot_delete_someone_elses_certificate(): void
    {
        [$firstUser] = $this->activatedProvider();
        [$secondUser] = $this->activatedProvider();

        $create = $this->actingAs($firstUser, 'sanctum')
            ->post('/api/v1/core/providers/me/certificates', ['name' => 'Owned By First']);

        $certificateId = $create->json('data.id');

        $this->actingAs($secondUser, 'sanctum')
            ->delete("/api/v1/core/providers/me/certificates/{$certificateId}")
            ->assertStatus(404);

        $this->assertDatabaseHas('provider_certificates', ['id' => $certificateId, 'deleted_at' => null]);
    }
}
