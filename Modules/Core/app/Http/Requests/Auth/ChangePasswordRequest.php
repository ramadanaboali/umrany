<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
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
            // Laravel's built-in `current_password` rule, guard-aware — the same mechanism
            // Admin\UpdateOwnProfileRequest uses (current_password:admin), scoped here to the
            // `sanctum` guard. Confirming it in the FormRequest (rather than re-checking in the
            // Service) keeps the 422 shape the standard validation-error one, not a bespoke error.
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }
}
