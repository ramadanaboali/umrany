<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;

final class StoreAdminRequest extends FormRequest
{
    /** Route-level `can:admins.create` middleware already gates this — see routes/admin.php. */
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
            'phone' => ['nullable', 'string', 'max:32', new SaudiOrEgyptianPhoneNumber],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
            // is_super_admin is deliberately not accepted here — it can only be granted via the
            // `core:admin:promote-super` console command, never through the web UI/API by anyone,
            // including another Super Admin. See docs/architecture/admin-portal.md.
        ];
    }
}
