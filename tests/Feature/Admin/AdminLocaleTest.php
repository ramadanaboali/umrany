<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Enums\Language;
use Modules\Core\Models\Admin;
use Tests\TestCase;

/**
 * See docs/decisions/0022-admin-dashboard-en-ar-localization.md — the actual acceptance
 * criterion is that the language choice survives logout/login (DB-backed), not just the session.
 */
class AdminLocaleTest extends TestCase
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

    public function test_default_language_is_english(): void
    {
        $admin = $this->admin()->refresh();

        $this->assertSame(Language::English, $admin->preferred_language);
    }

    public function test_admin_dashboard_renders_ltr_by_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('vendor/velzon/css/app.min.css', false)
            ->assertDontSee('vendor/velzon/css/app-rtl.min.css', false);
    }

    public function test_switching_language_persists_to_the_database_and_survives_logout(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/locale', ['language' => 'ar'])
            ->assertRedirect();

        $admin->refresh();
        $this->assertSame(Language::Arabic, $admin->preferred_language);

        $this->post('/admin/logout');

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('vendor/velzon/css/app-rtl.min.css', false)
            ->assertDontSee('vendor/velzon/css/app.min.css', false);
    }

    public function test_guest_language_switch_does_not_touch_an_admin_record(): void
    {
        $admin = $this->admin();

        $this->post('/admin/locale', ['language' => 'ar'])->assertRedirect();

        $admin->refresh();
        $this->assertSame(Language::English, $admin->preferred_language);
    }

    /**
     * The test client doesn't auto-carry a queued cookie from one response into the next request
     * the way a real browser would — withCookie() simulates that round-trip explicitly. See
     * App\Http\Controllers\Admin\LocaleController's admin_locale cookie.
     */
    public function test_guest_language_choice_persists_across_auth_pages_via_cookie(): void
    {
        $this->post('/admin/locale', ['language' => 'ar'])->assertRedirect();

        $this->withCookie('admin_locale', 'ar')
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false);

        $this->withCookie('admin_locale', 'ar')
            ->get('/admin/forgot-password')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_language_chosen_on_the_login_page_becomes_the_admins_saved_preference_on_login(): void
    {
        $admin = $this->admin();

        $this->withCookie('admin_locale', 'ar')
            ->post('/admin/login', ['email' => $admin->email, 'password' => 'Password123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(Language::Arabic, $admin->refresh()->preferred_language);
    }

    public function test_invalid_language_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/locale', ['language' => 'de'])
            ->assertSessionHasErrors('language');

        $admin->refresh();
        $this->assertSame(Language::English, $admin->preferred_language);
    }

    public function test_locale_does_not_leak_across_requests(): void
    {
        $admin = $this->admin();
        // preferred_language is deliberately not #[Fillable] — forceFill mirrors the repository's
        // own forceUpdate() convention for admin-only columns.
        $admin->forceFill(['preferred_language' => Language::Arabic])->save();

        $this->actingAs($admin, 'admin')->get('/admin')->assertSee('dir="rtl"', false);
        $this->post('/admin/logout');

        // A fresh, unauthenticated request in the same test process must not inherit Arabic —
        // mirrors the Octane guard-leak proof already established for FlushAuthenticationState.
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('dir="ltr"', false);
    }
}
