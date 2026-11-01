<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Models\ProviderSubcategory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for docs/decisions/0033-provider-category-admin-management.md — admin
 * management of the taxonomy previously seed-managed only.
 */
class ProviderCategoryManagementTest extends TestCase
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

    private function category(): ProviderCategory
    {
        return ProviderCategory::create(['slug' => 'category-'.uniqid(), 'name_en' => 'Contractor', 'name_ar' => 'مقاول']);
    }

    public function test_listing_categories_is_forbidden_without_permission(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin/provider-categories')->assertForbidden();
    }

    public function test_admin_with_permission_can_create_a_category(): void
    {
        Permission::findOrCreate('provider-categories.create', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['provider-categories.create']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin')
            ->post('/admin/provider-categories', ['slug' => 'landscaping', 'name_en' => 'Landscaping', 'name_ar' => 'تنسيق الحدائق', 'display_order' => 1])
            ->assertRedirect(route('admin.provider-categories.index'));

        $this->assertDatabaseHas('provider_categories', ['slug' => 'landscaping']);
    }

    public function test_admin_with_permission_can_update_a_category(): void
    {
        Permission::findOrCreate('provider-categories.update', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['provider-categories.update']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $category = $this->category();

        $this->actingAs($admin, 'admin')
            ->put("/admin/provider-categories/{$category->id}", ['slug' => $category->slug, 'name_en' => 'Renamed', 'name_ar' => 'مقاول', 'display_order' => 5])
            ->assertRedirect(route('admin.provider-categories.index'));

        $this->assertSame('Renamed', $category->refresh()->name_en);
    }

    public function test_admin_can_delete_a_category_not_assigned_to_any_provider(): void
    {
        Permission::findOrCreate('provider-categories.delete', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['provider-categories.delete']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $category = $this->category();

        $this->actingAs($admin, 'admin')
            ->delete("/admin/provider-categories/{$category->id}")
            ->assertRedirect(route('admin.provider-categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_a_category_still_assigned_to_a_provider_cannot_be_deleted(): void
    {
        Permission::findOrCreate('provider-categories.delete', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['provider-categories.delete']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $category = $this->category();
        $user = User::factory()->create();
        $provider = Provider::forceCreate(['user_id' => $user->id, 'company_name' => 'Acme']);
        $provider->categories()->attach($category->id);

        $this->actingAs($admin, 'admin')
            ->delete("/admin/provider-categories/{$category->id}")
            ->assertSessionHasErrors('category');

        $this->assertNotSoftDeleted($category);
    }

    public function test_admin_with_permission_can_create_a_subcategory_under_a_category(): void
    {
        Permission::findOrCreate('provider-categories.create', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['provider-categories.create']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $category = $this->category();

        $this->actingAs($admin, 'admin')
            ->post("/admin/provider-categories/{$category->id}/subcategories", ['slug' => 'general-contracting', 'name_en' => 'General Contracting', 'name_ar' => 'مقاولات عامة'])
            ->assertRedirect(route('admin.provider-categories.subcategories.index', $category));

        $this->assertDatabaseHas('provider_subcategories', ['provider_category_id' => $category->id, 'slug' => 'general-contracting']);
    }

    public function test_the_same_subcategory_slug_is_allowed_under_two_different_categories(): void
    {
        Permission::findOrCreate('provider-categories.create', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['provider-categories.create']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $categoryOne = $this->category();
        $categoryTwo = $this->category();

        $this->actingAs($admin, 'admin')
            ->post("/admin/provider-categories/{$categoryOne->id}/subcategories", ['slug' => 'general', 'name_en' => 'General', 'name_ar' => 'عام'])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post("/admin/provider-categories/{$categoryTwo->id}/subcategories", ['slug' => 'general', 'name_en' => 'General', 'name_ar' => 'عام'])
            ->assertRedirect(route('admin.provider-categories.subcategories.index', $categoryTwo));

        $this->assertDatabaseCount('provider_subcategories', 2);
    }

    public function test_a_subcategory_still_assigned_to_a_provider_cannot_be_deleted(): void
    {
        Permission::findOrCreate('provider-categories.delete', 'admin');
        $role = Role::findOrCreate('Ops', 'admin');
        $role->syncPermissions(['provider-categories.delete']);
        $admin = $this->admin();
        $admin->assignRole($role);

        $category = $this->category();
        $subcategory = ProviderSubcategory::create(['provider_category_id' => $category->id, 'slug' => 'general', 'name_en' => 'General', 'name_ar' => 'عام']);
        $user = User::factory()->create();
        $provider = Provider::forceCreate(['user_id' => $user->id, 'company_name' => 'Acme']);
        $provider->subcategories()->attach($subcategory->id);

        $this->actingAs($admin, 'admin')
            ->delete("/admin/provider-categories/{$category->id}/subcategories/{$subcategory->id}")
            ->assertSessionHasErrors('subcategory');

        $this->assertNotSoftDeleted($subcategory);
    }
}
