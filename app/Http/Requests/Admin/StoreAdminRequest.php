<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class StoreAdminRequest extends FormRequest
{
    /** Route-level `can:admins.manage` middleware already gates this — see routes/admin.php. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
            // Only meaningful if the acting admin is themselves a Super Admin — silently ignored
            // otherwise by Modules\Core\Actions\Admin\CreateAdmin, never trusted from input alone.
            'is_super_admin' => ['sometimes', 'boolean'],
        ];
    }
}
