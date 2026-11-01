<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Data\GovernmentVerificationResult;

/**
 * The single seam for the Saudi commercial-registration integration (FR-PROVIDER-004). Bound to
 * Modules\Core\Services\MockGovernmentVerificationClient in CoreServiceProvider until a real
 * gateway contract exists — see docs/decisions/0031-government-verification-mock-integration.md.
 * Swapping in a real implementation later needs no change anywhere else: everything calls this
 * contract, never a concrete client.
 */
interface GovernmentVerificationClient
{
    public function check(string $commercialRegistrationNumber): GovernmentVerificationResult;
}
