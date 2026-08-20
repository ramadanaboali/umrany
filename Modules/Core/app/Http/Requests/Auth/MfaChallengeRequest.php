<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class MfaChallengeRequest extends FormRequest
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
             * From the login response's "MFA required" payload.
             *
             * @example 9f8c1e2d3b4a5f6e7d8c9b0a1f2e3d4c
             */
            'challenge_token' => ['required', 'string'],

            /**
             * A TOTP code or a recovery code.
             *
             * @example 483920
             */
            'code' => ['required', 'string'],
        ];
    }
}
