<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Tests\TestCase;

/**
 * Super Admins bypass every permission check and aren't a "manageable" admin in the ordinary
 * sense — they're deliberately excluded from both the dashboard count and the admins listing.
 * See Admin::excludingSuperAdmins() and docs/architecture/admin-portal.md.
 */
class AdminListingTest extends TestCase
{
    private function admin(bool $superAdmin = false): Admin
    {
        return Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => $superAdmin,
        ]);
    }

    public function test_super_admins_are_excluded_from_the_dashboard_count(): void
    {
        $superAdmin = $this->admin(superAdmin: true);
        $this->admin(); // one regular admin

        $this->actingAs($superAdmin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee(trans_choice('admin.dashboard.admin_count', 1, ['count' => 1]));
    }

    public function test_super_admins_are_excluded_from_the_admins_index_listing(): void
    {
        $superAdmin = $this->admin(superAdmin: true);
        $regular = $this->admin();

        $this->actingAs($superAdmin, 'admin')
            ->get('/admin/admins')
            ->assertOk()
            ->assertSee($regular->name)
            ->assertDontSee($superAdmin->email);
    }
}
