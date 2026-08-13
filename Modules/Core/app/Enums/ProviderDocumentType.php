<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum ProviderDocumentType: string
{
    case CommercialRegistration = 'commercial_registration';
    case BusinessLicense = 'business_license';
    case NationalId = 'national_id';
    case Other = 'other';
}
