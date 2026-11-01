<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\GovernmentVerificationRequest;

/** @mixin GovernmentVerificationRequest */
final class GovernmentVerificationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'commercial_registration_number' => $this->commercial_registration_number,
            'imported_data' => $this->imported_data,
            'failure_reason' => $this->failure_reason,
            'requested_at' => $this->requested_at,
            'completed_at' => $this->completed_at,
            'applied_at' => $this->applied_at,
        ];
    }
}
