<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Enums\GovernmentVerificationStatus;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Models\Provider;
use Modules\Core\Notifications\ProviderProfilePublishedNotification;
use Modules\Core\Notifications\ProviderProfileSuspendedNotification;
use Modules\Core\Notifications\ProviderVerificationApprovedNotification;
use Modules\Core\Notifications\ProviderVerificationRejectedNotification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md — the
 * admin verification review + profile lifecycle screen.
 */
class ProviderManagementTest extends TestCase
{
    private function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
    }

    private function pendingProvider(): Provider
    {
        $user = User::factory()->create(['name' => 'Provider Owner']);
        $provider = Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => 'Acme Contracting',
            'status' => ProviderStatus::PendingReview,
        ]);
        $provider->verification()->create(['status' => ProviderVerificationStatus::Pending, 'submitted_at' => now()]);

        return $provider;
    }

    private function publishedProvider(): Provider
    {
        $user = User::factory()->create();
        $provider = Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => 'Acme Contracting',
            'status' => ProviderStatus::Published,
        ]);
        $provider->verification()->create(['status' => ProviderVerificationStatus::Approved, 'reviewed_at' => now()]);

        return $provider;
    }

    public function test_listing_providers_is_forbidden_without_providers_list_permission(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/providers')
            ->assertForbidden();
    }

    public function test_admin_with_providers_list_permission_can_list_providers(): void
    {
        Permission::findOrCreate('providers.list', 'admin');
        $role = Role::findOrCreate('Support', 'admin');
        $role->syncPermissions(['providers.list']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $this->pendingProvider();

        $this->actingAs($admin, 'admin')
            ->get('/admin/providers')
            ->assertOk()
            ->assertSee('Acme Contracting');
    }

    public function test_viewing_a_provider_is_forbidden_without_providers_view_permission(): void
    {
        Permission::findOrCreate('providers.list', 'admin');
        $role = Role::findOrCreate('Support', 'admin');
        $role->syncPermissions(['providers.list']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();

        $this->actingAs($admin, 'admin')
            ->get("/admin/providers/{$provider->id}")
            ->assertForbidden();
    }

    public function test_admin_with_providers_view_permission_can_view_a_provider(): void
    {
        Permission::findOrCreate('providers.view', 'admin');
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['providers.view']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();

        $this->actingAs($admin, 'admin')
            ->get("/admin/providers/{$provider->id}")
            ->assertOk()
            ->assertSee('Acme Contracting');
    }

    public function test_approving_a_provider_is_forbidden_without_providers_update_permission(): void
    {
        Permission::findOrCreate('providers.view', 'admin');
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['providers.view']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/approve")
            ->assertForbidden();
    }

    public function test_admin_with_providers_update_permission_can_approve_a_provider(): void
    {
        Notification::fake();
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/approve")
            ->assertRedirect(route('admin.providers.show', $provider))
            ->assertSessionHas('status');

        $provider->refresh();
        $this->assertSame(ProviderStatus::Published, $provider->status);
        $this->assertSame(ProviderVerificationStatus::Approved, $provider->verification->status);
        $this->assertSame($admin->id, $provider->verification->reviewed_by);
        $this->assertNotNull($provider->verification->reviewed_at);

        Notification::assertSentTo($provider->user, ProviderVerificationApprovedNotification::class);
        Notification::assertSentTo($provider->user, ProviderProfilePublishedNotification::class);
    }

    public function test_admin_with_providers_update_permission_can_reject_a_provider_with_a_reason(): void
    {
        Notification::fake();
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/reject", ['reason' => 'Documents are unclear'])
            ->assertRedirect(route('admin.providers.show', $provider))
            ->assertSessionHas('status');

        $provider->refresh();
        // Rejecting a verification never changes the profile's own status.
        $this->assertSame(ProviderStatus::PendingReview, $provider->status);
        $this->assertSame(ProviderVerificationStatus::Rejected, $provider->verification->status);
        $this->assertSame('Documents are unclear', $provider->verification->rejection_reason);
        $this->assertSame($admin->id, $provider->verification->reviewed_by);

        Notification::assertSentTo($provider->user, ProviderVerificationRejectedNotification::class);
    }

    public function test_reject_requires_a_reason(): void
    {
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(ProviderVerificationStatus::Pending, $provider->verification->refresh()->status);
    }

    public function test_admin_with_providers_update_permission_can_suspend_a_published_provider(): void
    {
        Notification::fake();
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/suspend", ['reason' => 'Policy violation'])
            ->assertRedirect(route('admin.providers.show', $provider))
            ->assertSessionHas('status');

        $provider->refresh();
        $this->assertSame(ProviderStatus::Suspended, $provider->status);
        // Suspend is independent of verification state — verification stays Approved.
        $this->assertSame(ProviderVerificationStatus::Approved, $provider->verification->status);

        Notification::assertSentTo($provider->user, ProviderProfileSuspendedNotification::class);
    }

    public function test_suspend_requires_a_reason(): void
    {
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/suspend", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(ProviderStatus::Published, $provider->refresh()->status);
    }

    public function test_admin_can_reactivate_a_suspended_provider(): void
    {
        Notification::fake();
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();
        $provider->forceFill(['status' => ProviderStatus::Suspended])->save();

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/reactivate")
            ->assertRedirect(route('admin.providers.show', $provider))
            ->assertSessionHas('status');

        $this->assertSame(ProviderStatus::Published, $provider->refresh()->status);

        Notification::assertSentTo($provider->user, ProviderProfilePublishedNotification::class);
    }

    public function test_updating_a_providers_company_info_is_forbidden_without_providers_update_permission(): void
    {
        Permission::findOrCreate('providers.view', 'admin');
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['providers.view']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();

        $this->actingAs($admin, 'admin')
            ->put("/admin/providers/{$provider->id}", ['company_name' => 'Attempted Name'])
            ->assertForbidden();
    }

    public function test_admin_with_providers_update_permission_can_edit_company_info(): void
    {
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();

        $this->actingAs($admin, 'admin')
            ->put("/admin/providers/{$provider->id}", ['company_name' => 'Renamed Co.'])
            ->assertRedirect(route('admin.providers.show', $provider))
            ->assertSessionHas('status');

        $this->assertSame('Renamed Co.', $provider->refresh()->company_name);
    }

    /**
     * Spec edge case: "Verification rejected multiple times" — a rejected provider must be able
     * to resubmit documents (ProviderVerificationStatus::canSubmit() already allows this from
     * Rejected), and a subsequent admin approval must still work end-to-end.
     */
    public function test_a_rejected_verification_can_be_resubmitted_and_then_approved(): void
    {
        Notification::fake();
        Storage::fake('public');
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();
        $provider->user->profile()->create(['full_name' => $provider->user->name]);
        $token = $provider->user->createToken('test')->plainTextToken;

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/reject", ['reason' => 'Documents are unclear'])
            ->assertRedirect(route('admin.providers.show', $provider));

        $this->assertSame(ProviderVerificationStatus::Rejected, $provider->verification->refresh()->status);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/providers/me/verification', [
                'documents' => [
                    ['type' => 'commercial_registration', 'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf')],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(ProviderVerificationStatus::Pending, $provider->verification->refresh()->status);

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/approve")
            ->assertRedirect(route('admin.providers.show', $provider));

        $provider->refresh();
        $this->assertSame(ProviderStatus::Published, $provider->status);
        $this->assertSame(ProviderVerificationStatus::Approved, $provider->verification->status);
    }

    /**
     * FR-PROVIDER-004: applying imported data must never overwrite a field the provider already
     * filled in — only address (left null here) gets merged, not company_name.
     */
    public function test_admin_can_apply_a_completed_government_verification_without_overwriting_existing_fields(): void
    {
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();
        $request = $provider->governmentVerificationRequests()->create([
            'commercial_registration_number' => 'CR-1234',
            'status' => GovernmentVerificationStatus::Completed,
            'source' => 'mock',
            'imported_data' => ['legal_business_name' => 'Should Not Overwrite', 'registered_address' => 'Imported Address', 'establishment_date' => '2010-05-01'],
            'requested_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/government-verification/{$request->id}/apply")
            ->assertRedirect(route('admin.providers.show', $provider));

        $provider->refresh();
        $this->assertSame('Acme Contracting', $provider->company_name);
        $this->assertSame('Imported Address', $provider->address);
        $this->assertSame(2010, $provider->year_established);
        $this->assertNotNull($request->refresh()->applied_at);
        $this->assertSame($admin->id, $request->applied_by_admin_id);
    }

    public function test_applying_a_government_verification_is_forbidden_without_providers_update_permission(): void
    {
        Permission::findOrCreate('providers.view', 'admin');
        $role = Role::findOrCreate('Viewer', 'admin');
        $role->syncPermissions(['providers.view']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();
        $request = $provider->governmentVerificationRequests()->create([
            'commercial_registration_number' => 'CR-1234',
            'status' => GovernmentVerificationStatus::Completed,
            'source' => 'mock',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/government-verification/{$request->id}/apply")
            ->assertForbidden();
    }

    public function test_applying_a_government_verification_that_belongs_to_a_different_provider_404s(): void
    {
        Permission::findOrCreate('providers.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['providers.update']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->publishedProvider();
        $otherProvider = $this->publishedProvider();
        $request = $otherProvider->governmentVerificationRequests()->create([
            'commercial_registration_number' => 'CR-9999',
            'status' => GovernmentVerificationStatus::Completed,
            'source' => 'mock',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/providers/{$provider->id}/government-verification/{$request->id}/apply")
            ->assertNotFound();
    }

    public function test_soft_deleted_provider_does_not_appear_in_the_listing(): void
    {
        Permission::findOrCreate('providers.list', 'admin');
        $role = Role::findOrCreate('Support', 'admin');
        $role->syncPermissions(['providers.list']);

        $admin = $this->admin();
        $admin->assignRole($role);

        $provider = $this->pendingProvider();
        $provider->delete();

        $this->actingAs($admin, 'admin')
            ->get('/admin/providers')
            ->assertOk()
            ->assertDontSee('Acme Contracting');
    }
}
