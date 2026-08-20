<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
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
             * Email or mobile number, resolved server-side.
             *
             * @example ahmed@example.com
             */
            'login' => ['required', 'string'],

            /**
             * @example Secr3tPass
             */
            'password' => ['required', 'string'],

            /**
             * Optional label for this session/device.
             *
             * @example iPhone 15
             */
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
