<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * Per FR-AUTH-002: a suspended account can never authenticate, regardless of how the
     * credentials were verified. Deletion is no longer a status value — a deleted account is a
     * real `deleted_at` soft-delete (see App\Models\User's SoftDeletes trait), which already
     * removes it from every normal query (including login lookups) without needing a status
     * check at all. See docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
