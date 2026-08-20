<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class DisableMfaRequest extends FormRequest
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
            'current_password' => ['required', 'current_password:sanctum'],
            // Either a 6-digit TOTP code or an "xxxxx-xxxxx" recovery code — a stolen session
            // alone must not be able to disable MFA on password knowledge alone.
            'code' => ['required', 'string'],
        ];
    }
}
