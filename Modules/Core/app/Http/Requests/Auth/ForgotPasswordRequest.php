<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately reveals whether an end-user account exists — an explicit, on-the-record product
 * decision to remove this endpoint's previous enumeration-safe posture (see
 * docs/decisions/0011-admin-forgot-password-reveals-account-existence.md's superseding note).
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
            /**
             * Email or mobile number on the account.
             *
             * @example ahmed@example.com
             */
            'login' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // `login` isn't tied to one column, so this can't be a plain Rule::exists() —
                    // mirrors EloquentUserRepository::findByLoginIdentifier()'s own matching.
                    // whereNull('deleted_at'): a soft-deleted account's identifier reads as
                    // "doesn't exist" (it's free for a new registration to reuse anyway).
                    $exists = User::query()
                        ->where('email', $value)
                        ->orWhere('mobile', $value)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $exists) {
                        $fail('No account exists with that email or mobile number.');
                    }
                },
            ],
        ];
    }
}
