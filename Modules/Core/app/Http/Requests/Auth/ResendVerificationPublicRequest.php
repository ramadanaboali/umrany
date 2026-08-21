<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public counterpart to the authenticated ResendVerificationCodeRequest — the recovery path for
 * an account that lost its only session before ever verifying. See docs/decisions/0026-block-
 * login-until-account-verified.md.
 */
final class ResendVerificationPublicRequest extends FormRequest
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
             * Email or mobile number on the account.
             *
             * @example ahmed@example.com
             */
            'login' => ['required', 'string'],
        ];
    }
}
