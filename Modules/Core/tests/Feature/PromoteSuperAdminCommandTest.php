<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Events\AdminPermissionsChanged;
use Modules\Core\Models\Admin;
use Tests\TestCase;

/**
 * The only remaining way is_super_admin can change at all — the web dashboard never accepts this
 * field from anyone. See docs/architecture/admin-portal.md.
 */
class PromoteSuperAdminCommandTest extends TestCase
{
    private function admin(bool $superAdmin = false, AdminStatus $status = AdminStatus::Active): Admin
    {
        return Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => $status,
            'is_super_admin' => $superAdmin,
        ]);
    }

    public function test_promotes_a_regular_admin_to_super_admin(): void
    {
        Event::fake([AdminPermissionsChanged::class]);
        $admin = $this->admin();

        $this->artisan('core:admin:promote-super', ['email' => $admin->email])->assertSuccessful();

        $this->assertTrue($admin->refresh()->is_super_admin);
        Event::assertDispatched(AdminPermissionsChanged::class, fn ($event) => $event->adminId === $admin->id);
    }

    public function test_revokes_super_admin_status_when_another_active_one_exists(): void
    {
        $admin = $this->admin(superAdmin: true);
        $this->admin(superAdmin: true); // ensures at least one other active super admin remains

        $this->artisan('core:admin:promote-super', ['email' => $admin->email, '--revoke' => true])
            ->assertSuccessful();

        $this->assertFalse($admin->refresh()->is_super_admin);
    }

    public function test_refuses_to_revoke_the_last_active_super_admin(): void
    {
        $onlySuperAdmin = $this->admin(superAdmin: true);

        $this->artisan('core:admin:promote-super', ['email' => $onlySuperAdmin->email, '--revoke' => true])
            ->assertFailed();

        $this->assertTrue($onlySuperAdmin->refresh()->is_super_admin);
    }

    public function test_fails_for_an_unknown_email(): void
    {
        $this->artisan('core:admin:promote-super', ['email' => 'nobody@umrany.test'])->assertFailed();
    }
}
