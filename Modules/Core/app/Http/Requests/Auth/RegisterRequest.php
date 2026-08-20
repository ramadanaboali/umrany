<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Enums\AccountType;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;
use Modules\Core\Support\PhoneNumber;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('mobile')) {
            $this->merge(['mobile' => PhoneNumber::normalize($this->string('mobile')->value())]);
        }

        // Default to "just a project owner" — the implicit capability every active, verified
        // account already has — when no explicit selection is made. See Modules\Core\Enums\
        // AccountType and docs/decisions/0016-account-type-intent-capture.md.
        if (! $this->filled('account_types')) {
            $this->merge(['account_types' => [AccountType::ProjectOwner->value]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Full name.
             *
             * @example Ahmed Al-Otaibi
             */
            'name' => ['required', 'string', 'max:255'],

            // FR-AUTH-001: register with mobile OR email — at least one is required, and each
            // must be unique platform-wide when present. whereNull('deleted_at'): a soft-deleted
            // account's identifier is free to reuse — see docs/decisions/0014-user-soft-deletes-
            // and-partial-unique-indexes.md.
            /**
             * Email address. Required if mobile is omitted.
             *
             * @example ahmed@example.com
             */
            'email' => ['nullable', 'required_without:mobile', 'email:rfc', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],

            /**
             * Saudi or Egyptian mobile number. Required if email is omitted.
             *
             * @example +966501234567
             */
            'mobile' => ['nullable', 'required_without:email', 'string', 'max:20', new SaudiOrEgyptianPhoneNumber, Rule::unique('users', 'mobile')->whereNull('deleted_at')],

            /**
             * Min 8 chars, mixed case, numbers — see config('core.password_policy.*').
             *
             * @example Secr3tPass
             */
            'password' => ['required', 'confirmed', Password::defaults()],

            'terms_accepted' => ['required', 'accepted'],

            // Captured intent only — never auto-activates a Provider profile. See
            // Modules\Core\Enums\AccountType.
            /**
             * "I am a:" — project_owner and/or provider. Captured intent only; POST .../providers
             * is still the real Provider activation step. Defaults to ["project_owner"].
             *
             * @example ["project_owner"]
             */
            'account_types' => ['sometimes', 'array', 'min:1'],
            'account_types.*' => ['string', 'distinct', Rule::enum(AccountType::class)],

            // Opts into TOTP MFA at registration time — the response includes a setup payload,
            // but MFA is not enforced at login until POST .../auth/mfa/confirm succeeds (see
            // Modules\Core\Services\MfaService::beginEnrollment() and docs/decisions/0012-totp-
            // mfa-with-two-step-login.md).
            /**
             * Begin TOTP MFA enrollment immediately — the response includes a setup payload, but
             * MFA isn't enforced until confirmed via POST .../auth/mfa/confirm.
             *
             * @example false
             */
            'mfa_enroll' => ['sometimes', 'boolean'],
        ];
    }
}
