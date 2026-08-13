<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum VerificationCodeType: string
{
    case Email = 'email';
    case Mobile = 'mobile';
}
