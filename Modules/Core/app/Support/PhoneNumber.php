<?php

declare(strict_types=1);

namespace Modules\Core\Support;

/**
 * Normalizes an already-validated (Modules\Core\Rules\SaudiOrEgyptianPhoneNumber) Saudi or
 * Egyptian mobile number to E.164 (`+9665XXXXXXXX` / `+201XXXXXXXXX`) at the point of persistence.
 * Deliberately not a model cast — a cast would silently rewrite the stored value on every
 * read/write, which is harder to reason about than one explicit call in the Service layer that
 * sets the column. See docs/decisions/0009-phone-number-validation.md.
 */
final class PhoneNumber
{
    private const SAUDI_PATTERN = '/^(?:\+966|00966|0)?(5\d{8})$/';

    private const EGYPTIAN_PATTERN = '/^(?:\+20|0020|0)?(1[0125]\d{8})$/';

    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stripped = preg_replace('/[\s\-]/', '', $value);

        if (preg_match(self::SAUDI_PATTERN, $stripped, $matches)) {
            return '+966'.$matches[1];
        }

        if (preg_match(self::EGYPTIAN_PATTERN, $stripped, $matches)) {
            return '+20'.$matches[1];
        }

        // Not a recognized shape — leave untouched rather than guessing; the validation Rule
        // should already have rejected this value before it ever reaches here.
        return $value;
    }
}
