<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Notifications\AdminResetPasswordNotification;
use Tests\TestCase;

/**
 * Covers the admin forgot-password flow's deliberate account-existence disclosure — see
 * docs/decisions/0011-admin-forgot-password-reveals-account-existence.md and
 * app/Http/Requests/Admin/ForgotPasswordRequest.php. This is a documented, scoped exception to
 * this codebase's usual enumeration-safe posture; the end-user API's equivalent endpoint stays
 * generic and is covered separately in Modules/Core/tests/Feature/Auth/PasswordResetTest.php.
 */
class AdminPasswordResetTest extends TestCase
{
    public function test_unknown_email_reveals_that_no_admin_account_exists(): void
    {
        $response = $this->post('/admin/forgot-password', [
            'email' => 'nobody@umrany.test',
        ]);

        $response->assertSessionHasErrors(['email' => 'No admin account exists with that email address.']);
    }

    public function test_known_email_sends_a_reset_link_and_reports_success(): void
    {
        Notification::fake();

        $admin = Admin::forceCreate([
            'name' => 'Ada',
            'email' => 'ada@umrany.test',
            'password' => Hash::make('CorrectPass1'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);

        $response = $this->post('/admin/forgot-password', [
            'email' => 'ada@umrany.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        // AdminResetPasswordNotification, not the stock Illuminate\Auth\Notifications\
        // ResetPassword — the stock one hardcodes route('password.reset'), which doesn't exist in
        // this app (admin reset routes are named admin.password.reset). Admin::
        // sendPasswordResetNotification() overrides which class gets sent specifically to avoid
        // that — see AdminResetPasswordNotification's docblock for the RouteNotFoundException this
        // once caused in real use, undetected by this test's own Notification::fake() (which never
        // renders toMail(), so a broken route reference here wouldn't have failed the assertion).
        Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
    }

    /**
     * Regression test for a real RouteNotFoundException hit in production use: the stock
     * ResetPassword notification's toMail() builds its link via route('password.reset', ...), a
     * route name this app never registers (ours is admin.password.reset). Notification::fake()
     * alone can't catch this — it never actually calls toMail(). Build the mail message for real
     * and assert the link resolves to the app's actual admin route.
     */
    public function test_reset_link_points_at_the_real_admin_reset_route(): void
    {
        $admin = Admin::forceCreate([
            'name' => 'Ada',
            'email' => 'ada@umrany.test',
            'password' => Hash::make('CorrectPass1'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);

        $mail = (new AdminResetPasswordNotification('test-token-123'))->toMail($admin);

        $this->assertStringContainsString('/admin/reset-password/test-token-123', $mail->actionUrl);
        $this->assertStringContainsString(urlencode($admin->email), $mail->actionUrl);
    }

    public function test_soft_deleted_admin_email_is_treated_as_unknown(): void
    {
        $admin = Admin::forceCreate([
            'name' => 'Ada',
            'email' => 'ada@umrany.test',
            'password' => Hash::make('CorrectPass1'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $admin->delete();

        $response = $this->post('/admin/forgot-password', [
            'email' => 'ada@umrany.test',
        ]);

        $response->assertSessionHasErrors(['email' => 'No admin account exists with that email address.']);
    }
}
