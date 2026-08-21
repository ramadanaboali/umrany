<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;
use Modules\Core\Support\PhoneNumber;

final class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->string('phone')->value())]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', new SaudiOrEgyptianPhoneNumber, Rule::unique('admins', 'phone')->ignore($this->route('admin'))->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(AdminStatus::class)],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
            // is_super_admin is deliberately not accepted here — see StoreAdminRequest.
        ];
    }
}
