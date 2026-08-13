<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum ProviderVerificationStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function isReviewable(): bool
    {
        return in_array($this, [self::Pending, self::UnderReview], true);
    }

    /**
     * A provider may (re)submit verification documents only from these states — not while a
     * review is already in progress (Pending/UnderReview), not once Approved (that's a distinct
     * "already verified" state, not something to silently resubmit over), and not while
     * Suspended (an admin action that requires admin reinstatement first, not a self-service
     * resubmission).
     */
    public function canSubmit(): bool
    {
        return in_array($this, [self::NotSubmitted, self::Rejected, self::Expired], true);
    }
}
