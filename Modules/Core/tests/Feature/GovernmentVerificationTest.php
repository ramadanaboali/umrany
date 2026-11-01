<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Enums\GovernmentVerificationStatus;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Notifications\GovernmentVerificationCompletedNotification;
use Tests\TestCase;

/**
 * FR-PROVIDER-004 — mock integration, see docs/decisions/0031-government-verification-mock-
 * integration.md. MockGovernmentVerificationClient is deterministic: a CR number ending in
 * "0000" simulates the service being unavailable, everything else succeeds.
 */
class GovernmentVerificationTest extends TestCase
{
    private function verifiedUser(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->profile()->create(['full_name' => $user->name]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    private function categoryId(): int
    {
        return ProviderCategory::create(['slug' => 'category-'.uniqid(), 'name_en' => 'Category', 'name_ar' => 'فئة'])->id;
    }

    public function test_a_provider_with_no_cr_number_cannot_request_government_verification(): void
    {
        [$user, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$this->categoryId()]]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers/me/government-verification')
            ->assertStatus(422)
            ->assertJsonValidationErrors('commercial_registration_number');
    }

    public function test_a_successful_check_creates_a_completed_request_and_notifies_the_provider(): void
    {
        Notification::fake();
        [$user, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$this->categoryId()], 'commercial_registration_number' => 'CR-1234']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers/me/government-verification')
            ->assertOk();

        $this->assertSame('completed', $response->json('data.status'));
        $this->assertSame('Registered Business CR-1234', $response->json('data.imported_data.legal_business_name'));
        $this->assertDatabaseCount('government_verification_requests', 1);

        Notification::assertSentTo($user, GovernmentVerificationCompletedNotification::class);
    }

    /**
     * Spec edge case: "Government verification service unavailable."
     */
    public function test_an_unavailable_service_creates_a_failed_request(): void
    {
        Notification::fake();
        [$user, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$this->categoryId()], 'commercial_registration_number' => 'CR-0000']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers/me/government-verification')
            ->assertOk();

        $this->assertSame('failed', $response->json('data.status'));
        $this->assertNotNull($response->json('data.failure_reason'));

        Notification::assertSentTo($user, GovernmentVerificationCompletedNotification::class);
    }

    public function test_a_completed_check_never_auto_applies_to_the_provider_profile(): void
    {
        [$user, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$this->categoryId()], 'commercial_registration_number' => 'CR-5678']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers/me/government-verification');

        $provider = $user->fresh()->provider()->first();
        $request = $provider->governmentVerificationRequests()->first();

        $this->assertSame(GovernmentVerificationStatus::Completed, $request->status);
        $this->assertNull($request->applied_at);
        // company_name stays exactly what the provider entered, never the mock's synthetic name.
        $this->assertSame('Acme', $provider->company_name);
    }

    public function test_can_view_the_latest_government_verification_on_my_profile(): void
    {
        [, $token] = $this->verifiedUser();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers', ['company_name' => 'Acme', 'category_ids' => [$this->categoryId()], 'commercial_registration_number' => 'CR-9999']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/core/providers/me/government-verification');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/providers/me')
            ->assertOk()
            ->assertJsonPath('data.government_verification.status', 'completed');
    }
}
