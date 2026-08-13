<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    /**
     * Per FR-AUTH-002: suspended and deleted accounts can never authenticate,
     * regardless of how the credentials were verified.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
