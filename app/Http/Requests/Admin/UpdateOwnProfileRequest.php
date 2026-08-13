<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class UpdateOwnProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            // Changing password is optional here — only validated when actually attempted.
            'current_password' => ['required_with:password', 'current_password:admin'],
            'password' => ['sometimes', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ];
    }
}
