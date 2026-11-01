<?php

declare(strict_types=1);

namespace Modules\Core\Data;

/**
 * The official company info a government commercial-registration check returns. Deliberately a
 * plain value object (not Spatie\LaravelData\Data) — this is only ever JSON-encoded into
 * `government_verification_requests.imported_data`, never cached, so none of the Data-object
 * serialization caveats documented on Modules\Core\Services\CapabilityService apply here.
 */
final class GovernmentBusinessData
{
    public function __construct(
        public readonly string $legalBusinessName,
        public readonly string $registrationStatus,
        public readonly ?string $establishmentDate,
        /** @var array<int, string> */
        public readonly array $businessActivities,
        public readonly ?string $registeredAddress,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'legal_business_name' => $this->legalBusinessName,
            'registration_status' => $this->registrationStatus,
            'establishment_date' => $this->establishmentDate,
            'business_activities' => $this->businessActivities,
            'registered_address' => $this->registeredAddress,
        ];
    }
}
