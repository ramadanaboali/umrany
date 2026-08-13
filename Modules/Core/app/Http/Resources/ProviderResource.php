<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Provider;

/** @mixin Provider */
final class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'logo_url' => $this->logo_path !== null ? Storage::disk('public')->url($this->logo_path) : null,
            'cover_url' => $this->cover_path !== null ? Storage::disk('public')->url($this->cover_path) : null,
            'social_links' => $this->social_links ?? [],
            'description' => $this->description,
            'country_id' => $this->country_id,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'commercial_registration_number' => $this->commercial_registration_number,
            'license_number' => $this->license_number,
            'tax_number' => $this->tax_number,
            'year_established' => $this->year_established,
            'employee_count' => $this->employee_count,
            'website' => $this->website,
            'status' => $this->status->value,
            'has_ecommerce_access' => $this->has_ecommerce_access,
            'has_erp_access' => $this->has_erp_access,
            'verification' => $this->whenLoaded('verification', fn () => new ProviderVerificationResource($this->verification)),
            'created_at' => $this->created_at,
        ];
    }
}
