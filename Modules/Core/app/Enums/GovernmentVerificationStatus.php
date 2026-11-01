<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum GovernmentVerificationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
