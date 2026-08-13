<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum VerificationCodePurpose: string
{
    case AccountVerification = 'account_verification';
    case PasswordReset = 'password_reset';
}
