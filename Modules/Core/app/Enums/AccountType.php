<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * The closed set of "I am a:" values registration accepts in `account_types` — captured intent
 * only, never an authorization source. A user selecting `Provider` still must call
 * `POST /providers` separately to actually activate a Provider profile; this is just echoed back
 * in the capabilities response as a signal of what the account owner said they wanted. See
 * docs/decisions/0016-account-type-intent-capture.md.
 */
enum AccountType: string
{
    case ProjectOwner = 'project_owner';
    case Provider = 'provider';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
