<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case PendingVerification = 'pending_verification';
    case Suspended = 'suspended';

    /**
     * Per FR-AUTH-002: a suspended account can never authenticate, regardless of how the
     * credentials were verified. Deletion is no longer a status value — a deleted account is a
     * real `deleted_at` soft-delete (see App\Models\User's SoftDeletes trait), which already
     * removes it from every normal query (including login lookups) without needing a status
     * check at all. See docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md.
     *
     * `PendingVerification` deliberately still returns `true` here — this method answers "is the
     * account itself administratively blocked" (suspension), a separate axis from "has the
     * account completed verification." `AuthService::login()` checks `User::hasVerifiedIdentity()`
     * as its own explicit gate, with its own distinct rejection message, rather than folding that
     * check into this one and losing the ability to tell a suspended account apart from an
     * unverified one. See docs/decisions/0026-block-login-until-account-verified.md.
     */
    public function canAuthenticate(): bool
    {
        return match ($this) {
            self::Active, self::PendingVerification => true,
            self::Suspended => false,
        };
    }
}
