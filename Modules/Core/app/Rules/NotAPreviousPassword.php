<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Services\PasswordHistoryService;

/**
 * Rejects a candidate password that matches the subject's current password or one of its last
 * `config('core.password_policy.history_count')` recorded hashes. Passes when $subject is null —
 * callers that don't yet know the subject at FormRequest-validation time (e.g. the unauthenticated
 * OTP-based reset flow) check reuse explicitly in the Service instead, after the code is consumed,
 * so a wrong/no code never leaks "this password was used before" — see
 * Modules\Core\Services\AuthService::resetPassword() and docs/decisions/0013-config-driven-
 * password-policy-and-history.md.
 */
final class NotAPreviousPassword implements ValidationRule
{
    public function __construct(
        private readonly ?Model $subject,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->subject === null || ! is_string($value)) {
            return;
        }

        if (app(PasswordHistoryService::class)->isReused($this->subject, $value)) {
            $fail(__('core::validation.password_reused'));
        }
    }
}
