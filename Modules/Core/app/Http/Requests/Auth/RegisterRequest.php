<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;

final class RegisterRequest extends FormRequest
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
            // FR-AUTH-001: register with mobile OR email — at least one is required, and each
            // must be unique platform-wide when present.
            'email' => ['nullable', 'required_without:mobile', 'email:rfc', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'required_without:email', 'string', 'max:20', new SaudiOrEgyptianPhoneNumber, 'unique:users,mobile'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }
}
