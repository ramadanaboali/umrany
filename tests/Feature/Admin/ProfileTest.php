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

    public function test_profile_can_be_updated_when_current_password_field_has_a_stray_value(): void
    {
        // Reproduces a real bug: browsers routinely autofill a saved "current password" into this
        // field even when the admin isn't touching the password section at all. Since `password`
        // itself is blank here, the stray/wrong current_password value must be ignored entirely,
        // not validated against the real stored hash.
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => 'Updated Name',
            'current_password' => 'SomeStaleAutofilledValue',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('Updated Name', $admin->refresh()->name);
    }

    public function test_wrong_current_password_is_rejected_when_actually_changing_password(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => $admin->name,
            'current_password' => 'WrongPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('OriginalPass123', $admin->refresh()->password));
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

    public function test_phone_already_used_by_another_admin_is_rejected(): void
    {
        Admin::forceCreate([
            'name' => 'Other Admin',
            'email' => 'other-'.uniqid().'@umrany.test',
            'phone' => '+966512345678',
            'password' => Hash::make('Password123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => $admin->name,
            // Different local format, same underlying number — must still be caught as a
            // duplicate since normalization happens before the uniqueness check runs.
            'phone' => '0512345678',
        ])->assertSessionHasErrors('phone');
    }

    public function test_keeping_own_existing_phone_is_not_flagged_as_a_duplicate(): void
    {
        $admin = Admin::forceCreate([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@umrany.test',
            'phone' => '+966512345678',
            'password' => Hash::make('OriginalPass123'),
            'status' => AdminStatus::Active,
            'is_super_admin' => false,
        ]);

        $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => $admin->name,
            'phone' => '+966512345678',
        ])->assertSessionDoesntHaveErrors();
    }
}
