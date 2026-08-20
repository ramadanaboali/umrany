<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Enums\ThemeMode;
use Modules\Core\Models\Admin;
use Tests\TestCase;

/**
 * See docs/decisions/0021-velzon-material-admin-theme.md — dark/light mode is now persisted
 * both per-browser (localStorage, tested only via the headless-browser repro that found the
 * original app.js crash, not covered here) and per-account via POST /admin/theme.
 */
class AdminThemeModeTest extends TestCase
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

    public function test_default_theme_mode_is_light(): void
    {
        $admin = $this->admin()->refresh();

        $this->assertSame(ThemeMode::Light, $admin->theme_mode);
    }

    public function test_authenticated_admin_can_persist_a_theme_mode_change(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->postJson('/admin/theme', ['mode' => 'dark'])
            ->assertNoContent();

        $this->assertSame(ThemeMode::Dark, $admin->refresh()->theme_mode);
    }

    public function test_saved_theme_mode_survives_logout_and_is_reflected_on_next_login(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson('/admin/theme', ['mode' => 'dark'])->assertNoContent();
        $this->post('/admin/logout');

        // The server-known mode is embedded as a pre-paint hint (App\Support\AdminTheme::
        // savedMode(), read by partials/theme-mode-boot.blade.php) — the actual data-layout-mode
        // attribute is only ever set client-side, so this is what a fresh, cookie-less client
        // (i.e. a different browser) would see.
        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('var saved = "dark"', false);
    }

    public function test_guest_theme_update_is_a_no_op_and_does_not_error(): void
    {
        $this->postJson('/admin/theme', ['mode' => 'dark'])->assertNoContent();
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $admin = $this->admin();

        // Plain post(), not postJson() — like every other admin route, /admin/theme renders
        // exceptions as a redirect + session errors, not JSON (bootstrap/app.php's
        // shouldRenderJsonWhen only applies to api/*). The client-side fetch() call is
        // fire-and-forget and never inspects this, but the test should assert what actually
        // happens rather than an API-style 422.
        $this->actingAs($admin, 'admin')
            ->post('/admin/theme', ['mode' => 'blue'])
            ->assertSessionHasErrors('mode');

        $this->assertSame(ThemeMode::Light, $admin->refresh()->theme_mode);
    }
}
