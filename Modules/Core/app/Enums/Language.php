<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum Language: string
{
    case Arabic = 'ar';
    case English = 'en';

    /**
     * FR-PROFILE-005: the UI switches RTL/LTR automatically based on this value.
     */
    public function direction(): string
    {
        return $this === self::Arabic ? 'rtl' : 'ltr';
    }
}
