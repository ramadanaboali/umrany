<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\ProviderCategory;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    private function verifiedUser(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['full_name' => $user->name]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /**
     * @return array<int, int>
     */
    private function categoryIds(int $count = 1): array
    {
        return collect(range(1, $count))
            ->map(fn (int $i) => ProviderCategory::create([
                'slug' => 'category-'.uniqid().'-'.$i,
                'name_en' => "Category {$i}",
                'name_ar' => "فئة {$i}",
            ])->id)
            ->all();
    }

    public function test_unverified_account_cannot_activate_a_provider_profile(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()])
            ->assertForbidden();
    }

    public function test_verified_account_can_activate_a_provider_profile(): void
    {
        [, $token] = $this->verifiedUser();
        $categoryIds = $this->categoryIds();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme Contracting', 'category_ids' => $categoryIds])
            ->assertStatus(201)
            ->assertJsonPath('data.company_name', 'Acme Contracting')
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.has_ecommerce_access', false)
            ->assertJsonPath('data.has_erp_access', false)
            ->assertJsonPath('data.categories.0.id', $categoryIds[0]);
    }

    public function test_activation_requires_at_least_one_category(): void
    {
        [, $token] = $this->verifiedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_ids');
    }

    public function test_account_cannot_activate_a_second_provider_profile(): void
    {
        [, $token] = $this->verifiedUser();
        $categoryIds = $this->categoryIds();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $categoryIds])
            ->assertStatus(201);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme Two', 'category_ids' => $categoryIds])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_name');
    }

    public function test_can_submit_verification_documents(): void
    {
        Storage::fake('public');
        [, $token] = $this->verifiedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/verification', [
                'documents' => [
                    ['type' => 'commercial_registration', 'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf')],
                ],
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('provider_documents', 1);
    }

    public function test_verification_cannot_be_resubmitted_while_pending(): void
    {
        Storage::fake('public');
        [$user, $token] = $this->verifiedUser();

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()]);
        $provider = $user->provider()->first();
        $provider->verification->update(['status' => ProviderVerificationStatus::Pending]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/verification', [
                'documents' => [
                    ['type' => 'commercial_registration', 'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf')],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('documents');
    }

    public function test_can_activate_with_valid_social_links(): void
    {
        [, $token] = $this->verifiedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', [
                'company_name' => 'Acme',
                'category_ids' => $this->categoryIds(),
                'social_links' => ['facebook' => 'https://facebook.com/acme', 'instagram' => 'https://instagram.com/acme'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.social_links.facebook', 'https://facebook.com/acme');
    }

    public function test_activation_rejects_unknown_social_platform(): void
    {
        [, $token] = $this->verifiedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', [
                'company_name' => 'Acme',
                'category_ids' => $this->categoryIds(),
                'social_links' => ['myspace' => 'https://myspace.com/acme'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('social_links');
    }

    public function test_can_update_social_links(): void
    {
        [, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/providers/me', ['social_links' => ['linkedin' => 'https://linkedin.com/company/acme']])
            ->assertOk()
            ->assertJsonPath('data.social_links.linkedin', 'https://linkedin.com/company/acme');
    }

    public function test_can_update_categories(): void
    {
        [, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()]);

        $newCategoryIds = $this->categoryIds(2);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/providers/me', ['category_ids' => $newCategoryIds])
            ->assertOk()
            ->assertJsonCount(2, 'data.categories');
    }

    public function test_can_upload_and_remove_logo(): void
    {
        Storage::fake('public');
        [, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/logo', ['logo' => UploadedFile::fake()->image('logo.jpg')])
            ->assertOk()
            ->assertJsonPath('data.logo_url', fn ($url) => $url !== null);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->delete('/api/v1/core/providers/me/logo')
            ->assertStatus(204);
    }

    public function test_can_upload_and_remove_cover(): void
    {
        Storage::fake('public');
        [, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/cover', ['cover' => UploadedFile::fake()->image('cover.jpg')])
            ->assertOk()
            ->assertJsonPath('data.cover_url', fn ($url) => $url !== null);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->delete('/api/v1/core/providers/me/cover')
            ->assertStatus(204);
    }

    /**
     * Regression test for docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md's
     * extension to Provider — `providers` already had SoftDeletes but was left on a plain unique
     * `user_id` index, permanently blocking a soft-deleted provider's former owner from ever
     * activating a new one. No HTTP delete-provider endpoint exists yet, so the soft-delete itself
     * is done directly on the model (matching how this would actually occur once one is built).
     */
    public function test_same_user_can_reactivate_a_provider_after_the_previous_one_is_soft_deleted(): void
    {
        [$user, $token] = $this->verifiedUser();
        $categoryIds = $this->categoryIds();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $categoryIds])
            ->assertStatus(201);

        $provider = $user->provider()->first();
        $provider->delete();
        $this->assertSoftDeleted($provider);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme Reborn', 'category_ids' => $categoryIds])
            ->assertStatus(201);
    }

    /**
     * Same fix, the other affected column — a soft-deleted provider's commercial_registration_
     * number must be free for a *different* account to reuse.
     */
    public function test_a_different_account_can_reuse_a_soft_deleted_providers_cr_number(): void
    {
        [$firstOwner, $firstToken] = $this->verifiedUser();
        [, $secondToken] = $this->verifiedUser();
        $categoryIds = $this->categoryIds();

        $this->withHeader('Authorization', "Bearer {$firstToken}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $categoryIds, 'commercial_registration_number' => 'CR-REUSED'])
            ->assertStatus(201);

        $firstOwner->provider()->first()->delete();

        $this->withHeader('Authorization', "Bearer {$secondToken}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Someone Else', 'category_ids' => $categoryIds, 'commercial_registration_number' => 'CR-REUSED'])
            ->assertStatus(201);
    }

    /**
     * Spec edge case: "Duplicate Commercial Registration Number." Two simultaneously *active*
     * providers must never share one — distinct from the soft-delete-reuse case above, where the
     * first provider's row is gone.
     */
    public function test_a_second_active_provider_cannot_reuse_another_active_providers_cr_number(): void
    {
        [, $firstToken] = $this->verifiedUser();
        [, $secondToken] = $this->verifiedUser();
        $categoryIds = $this->categoryIds();

        $this->withHeader('Authorization', "Bearer {$firstToken}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $categoryIds, 'commercial_registration_number' => 'CR-ACTIVE'])
            ->assertStatus(201);

        $this->withHeader('Authorization', "Bearer {$secondToken}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Someone Else', 'category_ids' => $categoryIds, 'commercial_registration_number' => 'CR-ACTIVE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('commercial_registration_number');
    }

    /**
     * Spec edge case: "Provider changes business category after approval." No re-review is
     * required — a category change on an already-Published, Approved provider must not touch
     * publication or verification status. This documents that as the deliberate behavior, not an
     * accidental gap (docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md).
     */
    public function test_changing_categories_after_publication_does_not_unpublish_the_provider(): void
    {
        [$user, $token] = $this->verifiedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => $this->categoryIds()]);

        $provider = $user->provider()->first();
        $provider->forceFill(['status' => ProviderStatus::Published])->save();
        $provider->verification->update(['status' => ProviderVerificationStatus::Approved]);

        $newCategoryIds = $this->categoryIds(2);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/providers/me', ['category_ids' => $newCategoryIds])
            ->assertOk();

        $provider->refresh();
        $this->assertSame(ProviderStatus::Published, $provider->status);
        $this->assertSame(ProviderVerificationStatus::Approved, $provider->verification->status);
        $this->assertEqualsCanonicalizing($newCategoryIds, $provider->categories()->pluck('provider_categories.id')->all());
    }
}
