<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Models\SiteSetting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteSettingsManagementTest extends TestCase
{
    private function admin(array $permissions = []): Admin
    {
        $admin = Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);

        if ($permissions !== []) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'admin');
            }

            $role = Role::findOrCreate('Test role '.uniqid(), 'admin');
            $role->syncPermissions($permissions);
            $admin->syncRoles([$role->name]);
        }

        return $admin;
    }

    public function test_viewing_settings_is_forbidden_without_permission(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_sees_the_current_settings(): void
    {
        SiteSetting::current()->update(['site_name' => 'Umrany Test']);
        $admin = $this->admin(['settings.view']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Umrany Test');
    }

    public function test_updating_settings_is_forbidden_without_update_permission(): void
    {
        $admin = $this->admin(['settings.view']);

        $this->actingAs($admin, 'admin')
            ->put('/admin/settings', ['site_name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_admin_with_update_permission_can_update_settings(): void
    {
        $admin = $this->admin(['settings.view', 'settings.update']);

        $this->actingAs($admin, 'admin')
            ->put('/admin/settings', [
                'site_name' => 'Umrany Updated',
                'site_title' => 'Umrany | Home',
                'contact_email' => 'hello@umrany.test',
                'contact_phone' => '+966500000000',
                'contact_address' => 'Riyadh, Saudi Arabia',
                'social_links' => ['facebook' => 'https://facebook.com/umrany'],
            ])
            ->assertRedirect();

        $setting = SiteSetting::current();
        $this->assertSame('Umrany Updated', $setting->site_name);
        $this->assertSame('Umrany | Home', $setting->site_title);
        $this->assertSame('hello@umrany.test', $setting->contact_email);
        $this->assertSame(['facebook' => 'https://facebook.com/umrany'], $setting->social_links);
    }

    public function test_unknown_social_platform_is_rejected(): void
    {
        $admin = $this->admin(['settings.view', 'settings.update']);

        $this->actingAs($admin, 'admin')
            ->put('/admin/settings', [
                'site_name' => 'Umrany',
                'social_links' => ['myspace' => 'https://myspace.com/umrany'],
            ])
            ->assertSessionHasErrors('social_links');
    }

    public function test_admin_can_upload_and_remove_the_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin(['settings.view', 'settings.update']);

        $this->actingAs($admin, 'admin')
            ->put('/admin/settings', [
                'site_name' => 'Umrany',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect();

        $setting = SiteSetting::current();
        $this->assertNotNull($setting->logo_path);
        Storage::disk('public')->assertExists($setting->logo_path);

        $this->actingAs($admin, 'admin')
            ->delete('/admin/settings/logo')
            ->assertRedirect();

        $this->assertNull(SiteSetting::current()->logo_path);
    }
}
