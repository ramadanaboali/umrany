<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    private function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'password' => Hash::make('OriginalPass123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
    }

    public function test_profile_can_be_updated_with_blank_password_fields(): void
    {
        $admin = $this->admin();
        $originalHash = $admin->password;

        $response = $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => 'Updated Name',
            'phone' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $admin->refresh();
        $this->assertSame('Updated Name', $admin->name);
        $this->assertSame($originalHash, $admin->password);
    }

    public function test_password_can_still_be_changed_when_provided(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => $admin->name,
            'current_password' => 'OriginalPass123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertTrue(Hash::check('NewPassword123', $admin->refresh()->password));
    }

    public function test_invalid_phone_format_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => $admin->name,
            'phone' => '123456',
        ])->assertSessionHasErrors('phone');
    }

    public function test_valid_saudi_phone_is_accepted_and_normalized(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => $admin->name,
            'phone' => '0512345678',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('+966512345678', $admin->refresh()->phone);
    }
}
