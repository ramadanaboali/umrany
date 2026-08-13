<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Public-visibility state of a Provider profile — distinct from
 * ProviderVerificationStatus, which is the document-review workflow state.
 * A provider can only be Published once its verification is Approved; the
 * two are checked together in Provider::isPubliclyVisible(), never assumed
 * from one field alone.
 */
enum ProviderStatus: string
{
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Suspended = 'suspended';
}
