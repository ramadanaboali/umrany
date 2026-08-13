<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoleRequest extends FormRequest
{
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
            // Role names are examples/admin-created, never hard-coded (docs/business/personas.md
            // § Note on Admin Roles and Permissions) — this is genuinely free text.
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'admin')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'admin')],
        ];
    }
}
