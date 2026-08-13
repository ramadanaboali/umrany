<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\ProviderVerification;

/** @mixin ProviderVerification */
final class ProviderVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
