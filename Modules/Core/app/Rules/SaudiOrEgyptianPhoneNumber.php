<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts a mobile number in either Saudi or Egyptian format, local or international — the two
 * markets this platform currently serves (see docs/decisions/0009-phone-number-validation.md).
 * Validation only; normalizing an accepted value to E.164 is a separate concern handled by
 * Modules\Core\Support\PhoneNumber::normalize() at the point of persistence, not here.
 */
final class SaudiOrEgyptianPhoneNumber implements ValidationRule
{
    /** Local `05XXXXXXXX` or international `+9665XXXXXXXX`/`009665XXXXXXXX`. */
    private const SAUDI_PATTERN = '/^(?:\+966|00966|0)?5\d{8}$/';

    /** Local `01[0125]XXXXXXXX` or international `+201[0125]XXXXXXXX`/`00201[0125]XXXXXXXX`. */
    private const EGYPTIAN_PATTERN = '/^(?:\+20|0020|0)?1[0125]\d{8}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('core::validation.invalid_phone_number'));

            return;
        }

        $normalized = preg_replace('/[\s\-]/', '', $value);

        if (! preg_match(self::SAUDI_PATTERN, $normalized) && ! preg_match(self::EGYPTIAN_PATTERN, $normalized)) {
            $fail(__('core::validation.invalid_phone_number'));
        }
    }
}
