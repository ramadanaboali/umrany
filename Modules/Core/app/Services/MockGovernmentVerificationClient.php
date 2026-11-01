<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Contracts\GovernmentVerificationClient;
use Modules\Core\Data\GovernmentBusinessData;
use Modules\Core\Data\GovernmentVerificationResult;

/**
 * Placeholder implementation — no real Saudi commercial-registration integration exists yet (no
 * contract/credentials chosen, see docs/decisions/0031-government-verification-mock-integration.
 * md). Deterministic on the CR number (not random) so callers/tests get a repeatable result: a CR
 * number ending in "0000" simulates the service being unavailable (FR-PROVIDER-004's own "edge
 * case" list), everything else simulates a successful lookup with plausible, obviously-synthetic
 * data. Swap the CoreServiceProvider binding to a real client when one exists — nothing else
 * needs to change.
 */
final class MockGovernmentVerificationClient implements GovernmentVerificationClient
{
    public function check(string $commercialRegistrationNumber): GovernmentVerificationResult
    {
        if (str_ends_with($commercialRegistrationNumber, '0000')) {
            return GovernmentVerificationResult::failure(
                'Government verification service is temporarily unavailable. Please try again later.',
            );
        }

        return GovernmentVerificationResult::success(new GovernmentBusinessData(
            legalBusinessName: 'Registered Business '.$commercialRegistrationNumber,
            registrationStatus: 'active',
            establishmentDate: '2015-01-01',
            businessActivities: ['General Contracting', 'Construction Consultancy'],
            registeredAddress: 'Mock Registered Address, Riyadh, Saudi Arabia',
        ));
    }
}
