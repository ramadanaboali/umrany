<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
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
            /**
             * Same identifier used to request the code.
             *
             * @example ahmed@example.com
             */
            'login' => ['required', 'string'],
            'code' => ['required', 'digits:6'],

            /**
             * @example NewSecr3t1
             */
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
