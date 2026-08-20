<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\Provider;
use Tests\TestCase;

class CapabilityTest extends TestCase
{
    public function test_plain_registered_user_has_no_provider_capabilities(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/capabilities')
            ->assertOk()
            ->assertJson(['data' => [
                'is_provider' => false,
                'provider_verified' => false,
                'has_ecommerce_access' => false,
                'has_erp_access' => false,
            ]]);
    }

    public function test_provider_with_approved_verification_and_published_status_is_reported_verified(): void
    {
        $user = User::factory()->create();
        $provider = Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => 'Acme',
            'status' => ProviderStatus::Published,
            'has_ecommerce_access' => true,
            'has_erp_access' => false,
        ]);
        $provider->verification()->create(['status' => ProviderVerificationStatus::Approved]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/capabilities')
            ->assertOk()
            ->assertJson(['data' => [
                'is_provider' => true,
                'provider_verified' => true,
                'has_ecommerce_access' => true,
                'has_erp_access' => false,
            ]]);
    }

    public function test_provider_pending_review_is_not_reported_verified_even_if_approved(): void
    {
        $user = User::factory()->create();
        $provider = Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => 'Acme',
            'status' => ProviderStatus::PendingReview,
        ]);
        $provider->verification()->create(['status' => ProviderVerificationStatus::Approved]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/capabilities')
            ->assertJsonPath('data.provider_verified', false);
    }

    /**
     * Exercises the whole cache-aside + invalidation chain for real: populate the cache while
     * the account has no provider, activate a provider (which fires
     * Modules\Core\Events\UserCapabilitiesChanged), then confirm the *next* read reflects the
     * change instead of the stale cached "not a provider" value. QUEUE_CONNECTION=sync in
     * phpunit.xml means the queued listener runs inline, no Queue::fake() needed.
     */
    public function test_capabilities_cache_is_invalidated_when_provider_is_activated(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['full_name' => $user->name]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/capabilities')
            ->assertJsonPath('data.is_provider', false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme'])
            ->assertStatus(201);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/capabilities')
            ->assertJsonPath('data.is_provider', true);
    }

    public function test_capabilities_reports_is_project_owner_true_and_account_types_for_a_verified_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_types' => ['project_owner']]);
        $user->profile()->create(['full_name' => $user->name]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/capabilities')
            ->assertOk()
            ->assertJson(['data' => [
                'is_project_owner' => true,
                'account_types' => ['project_owner'],
            ]]);
    }

    /**
     * `GET /me/capabilities` sits behind the `account.verified` middleware (routes/api.php), so an
     * account that hasn't verified either channel yet — even with an otherwise-valid token and an
     * "active" status — can never reach far enough to see an `is_project_owner: false` body from
     * this endpoint; it is rejected before the controller/service ever runs. This is the "verified
     * vs. unverified" contrast for this endpoint: unverified never gets a capabilities payload at
     * all, verified does (see the test above).
     */
    public function test_capabilities_endpoint_rejects_an_unverified_user_before_reporting_capabilities(): void
    {
        $user = User::factory()->unverified()->create(['account_types' => ['project_owner']]);
        $user->profile()->create(['full_name' => $user->name]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/me/capabilities')
            ->assertStatus(403);
    }
}
