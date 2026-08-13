<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    public function test_admin_can_log_in_with_correct_credentials(): void
    {
        $admin = Admin::forceCreate([
            'name' => 'Ada',
            'email' => 'ada@umrany.test',
            'password' => Hash::make('CorrectPass1'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'ada@umrany.test',
            'password' => 'CorrectPass1',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_login_fails_with_wrong_password(): void
    {
        Admin::forceCreate([
            'name' => 'Ada',
            'email' => 'ada@umrany.test',
            'password' => Hash::make('CorrectPass1'),
            'status' => AdminStatus::Active,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'ada@umrany.test',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_suspended_admin_cannot_log_in(): void
    {
        Admin::forceCreate([
            'name' => 'Ada',
            'email' => 'ada@umrany.test',
            'password' => Hash::make('CorrectPass1'),
            'status' => AdminStatus::Suspended,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'ada@umrany.test',
            'password' => 'CorrectPass1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_guest_is_redirected_to_login_when_visiting_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_authenticated_admin_can_log_out(): void
    {
        $admin = Admin::forceCreate([
            'name' => 'Ada',
            'email' => 'ada@umrany.test',
            'password' => Hash::make('CorrectPass1'),
            'status' => AdminStatus::Active,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $this->assertGuest('admin');
    }
}
