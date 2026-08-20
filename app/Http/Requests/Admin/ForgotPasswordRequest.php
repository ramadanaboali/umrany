<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deliberately reveals whether an admin account exists — see docs/decisions/0011-admin-forgot-
 * password-reveals-account-existence.md for the scoped, documented exception to this codebase's
 * usual enumeration-safe posture (the end-user API's forgot-password endpoint is untouched and
 * stays generic).
 */
final class ForgotPasswordRequest extends FormRequest
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
            // whereNull('deleted_at'): a soft-deleted admin's email must not validate as a real,
            // reachable account.
            'email' => ['required', 'email:rfc', Rule::exists('admins', 'email')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => __('admin.auth.no_account_with_email'),
        ];
    }
}
