<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Models\PasswordHistory;
use Tests\TestCase;

/**
 * Mirrors Modules\Core\Tests\Feature\Auth\PasswordHistoryTest for the admin portal — the same
 * `Modules\Core\Services\PasswordHistoryService` and `password_histories` morph table are shared
 * between `App\Models\User` and `Modules\Core\Models\Admin`. Two distinct admin-side flows set a
 * password:
 *
 * - Self-service profile edit: PUT /admin/profile (App\Http\Controllers\Admin\ProfileController,
 *   App\Http\Requests\Admin\UpdateOwnProfileRequest) — reuse checked at the FormRequest layer via
 *   `new NotAPreviousPassword(Auth::guard('admin')->user())`, same placement as the end-user
 *   change-password request.
 * - Emailed-link reset: POST /admin/reset-password (App\Http\Controllers\Admin\
 *   PasswordResetController::storeReset(), backed by Laravel's own `Password::broker('admins')`)
 *   — reuse checked inside `Modules\Core\Services\Admin\AdminAuthService::resetPassword()`, after
 *   the broker has already verified the token+email pair, thrown as a ValidationException the
 *   controller catches and turns into `back()->withErrors(...)`.
 *
 * Both are session-guard/Blade flows, not JSON — assertions use assertSessionHasErrors /
 * assertRedirect, not assertJsonValidationErrors, per this directory's convention (see
 * AdminAuthTest, AdminManagementTest).
 */
class AdminPasswordHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // POST /admin/reset-password shares the same 'password-reset' named rate limiter as the
        // end-user endpoints (keyed only by IP, 3/minute) — disabled here for consistency/safety,
        // matching Modules\Core\Tests\Feature\Auth\PasswordHistoryTest.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeAdmin(string $email, string $password): Admin
    {
        return Admin::forceCreate([
            'name' => 'Ada',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
    }

    public function test_admin_changing_their_own_password_to_the_current_password_is_rejected(): void
    {
        $admin = $this->makeAdmin('ada@umrany.test', 'CorrectPass1');

        $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => 'Ada',
            'current_password' => 'CorrectPass1',
            'password' => 'CorrectPass1',
            'password_confirmation' => 'CorrectPass1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('CorrectPass1', $admin->refresh()->password));
    }

    public function test_admin_reusing_a_password_within_history_count_is_rejected_but_an_older_one_is_allowed(): void
    {
        config(['core.password_policy.history_count' => 2]);

        $admin = $this->makeAdmin('ada@umrany.test', 'PasswordD1');

        // Seed history directly with explicit, well-separated created_at values — see
        // Modules\Core\Tests\Feature\Auth\PasswordHistoryTest for why (created_at only has
        // second-level DB resolution, several requests here could tie on the same second).
        foreach ([
            ['password' => 'PasswordC1', 'created_at' => now()->subMinute()],
            ['password' => 'PasswordB1', 'created_at' => now()->subMinutes(2)],
            ['password' => 'PasswordA1', 'created_at' => now()->subMinutes(3)],
        ] as $entry) {
            PasswordHistory::create([
                'authenticatable_type' => $admin->getMorphClass(),
                'authenticatable_id' => $admin->id,
                'password_hash' => Hash::make($entry['password']),
                'created_at' => $entry['created_at'],
            ]);
        }

        $attempt = fn (string $password) => $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => 'Ada',
            'current_password' => 'PasswordD1',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        // The live current password — always checked directly, independent of history_count.
        $attempt('PasswordD1')->assertSessionHasErrors('password');

        // Within the last 2 recorded hashes (C, B) — rejected.
        $attempt('PasswordC1')->assertSessionHasErrors('password');
        $attempt('PasswordB1')->assertSessionHasErrors('password');

        // Older than history_count=2 (3rd back) — allowed.
        $attempt('PasswordA1')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(Hash::check('PasswordA1', $admin->refresh()->password));
    }

    public function test_admin_history_count_zero_disables_the_reuse_check(): void
    {
        config(['core.password_policy.history_count' => 0]);

        $admin = $this->makeAdmin('ada@umrany.test', 'CorrectPass1');

        $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => 'Ada',
            'current_password' => 'CorrectPass1',
            'password' => 'CorrectPass1',
            'password_confirmation' => 'CorrectPass1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue(Hash::check('CorrectPass1', $admin->refresh()->password));
    }

    public function test_admin_emailed_link_reset_rejects_a_reused_password(): void
    {
        $admin = $this->makeAdmin('ada@umrany.test', 'CorrectPass1');
        $token = Password::broker('admins')->createToken($admin);

        $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => 'ada@umrany.test',
            'password' => 'CorrectPass1',
            'password_confirmation' => 'CorrectPass1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(
            Hash::check('CorrectPass1', $admin->refresh()->password),
            'the reused-password attempt must not have changed the stored password',
        );
    }

    public function test_admin_emailed_link_reset_accepts_a_genuinely_new_password(): void
    {
        $admin = $this->makeAdmin('ada@umrany.test', 'OldPassword1');
        $token = Password::broker('admins')->createToken($admin);

        $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => 'ada@umrany.test',
            'password' => 'BrandNewPassword1',
            'password_confirmation' => 'BrandNewPassword1',
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('BrandNewPassword1', $admin->refresh()->password));
    }
}
