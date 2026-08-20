<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Models\SiteSetting;
use Tests\TestCase;

/**
 * The sidebar/topbar/login-page brand (resources/views/admin/partials/brand.blade.php and the
 * guest layout's own auth-logo block) shows the admin's uploaded SiteSetting logo when one
 * exists, or the localized brand name as text otherwise — never Velzon's own placeholder
 * artwork. See the comment thread on docs/decisions/0021-velzon-material-admin-theme.md.
 */
class BrandingDisplayTest extends TestCase
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

    public function test_dashboard_shows_brand_name_text_when_no_logo_is_configured(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee(__('admin.common.app_name'))
            ->assertDontSee('vendor/velzon/images/logo-dark.png', false)
            ->assertDontSee('vendor/velzon/images/logo-light.png', false);
    }

    public function test_dashboard_shows_the_uploaded_logo_image_when_one_exists(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('logo.png');
        $path = $file->store('site-settings/logo', 'public');
        SiteSetting::current()->update(['logo_path' => $path]);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk();

        $response->assertSee(Storage::disk('public')->url($path), false);
    }

    public function test_login_page_shows_brand_name_text_when_no_logo_is_configured(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee(__('admin.common.app_name'))
            ->assertDontSee('vendor/velzon/images/logo-light.png', false);
    }
}
