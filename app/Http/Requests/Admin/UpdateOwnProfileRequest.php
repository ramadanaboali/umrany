<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Modules\Core\Rules\NotAPreviousPassword;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;
use Modules\Core\Support\PhoneNumber;

final class UpdateOwnProfileRequest extends FormRequest
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
        // `current_password` must only be validated when a new password is actually being
        // submitted — a browser routinely autofills a saved "current password" value into this
        // field even when the admin isn't touching the password section at all, and
        // `required_with:password` alone only controls whether the field is *required*, not
        // whether `current_password:admin` runs against whatever stray value showed up. Without
        // this branch, that autofilled (and often stale) value gets validated against the real
        // stored hash and fails with "The password is incorrect" on a plain name/phone edit.
        $changingPassword = filled($this->input('password'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', new SaudiOrEgyptianPhoneNumber, Rule::unique('admins', 'phone')->ignore(Auth::guard('admin')->id())->whereNull('deleted_at')],
            'current_password' => $changingPassword ? ['required', 'current_password:admin'] : ['nullable'],
            'password' => ['sometimes', 'nullable', 'confirmed', PasswordRule::defaults(), new NotAPreviousPassword(Auth::guard('admin')->user())],
        ];
    }
}
