<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Data\UserCapabilities;

/** @mixin UserCapabilities */
final class UserCapabilitiesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_provider' => $this->isProvider,
            'provider_verified' => $this->providerVerified,
            'has_ecommerce_access' => $this->hasEcommerceAccess,
            'has_erp_access' => $this->hasErpAccess,
            'max_project_offers' => $this->maxProjectOffers,
        ];
    }
}
