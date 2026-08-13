<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum AdminStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
