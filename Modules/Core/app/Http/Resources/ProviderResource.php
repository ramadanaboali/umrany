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
            'categories' => $this->whenLoaded('categories', fn () => ProviderCategoryResource::collection($this->categories)),
            'subcategories' => $this->whenLoaded('subcategories', fn () => ProviderSubcategoryResource::collection($this->subcategories)),
            'portfolios' => $this->whenLoaded('portfolios', fn () => ProviderPortfolioResource::collection($this->portfolios)),
            'certificates' => $this->whenLoaded('certificates', fn () => ProviderCertificateResource::collection($this->certificates)),
            'verification' => $this->whenLoaded('verification', fn () => new ProviderVerificationResource($this->verification)),
            'government_verification' => $this->whenLoaded('latestGovernmentVerificationRequest', fn () => $this->latestGovernmentVerificationRequest !== null
                ? new GovernmentVerificationRequestResource($this->latestGovernmentVerificationRequest)
                : null),
            'statistics' => $this->statistics(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Deliberately partial and honest (docs/decisions/0030-provider-taxonomy-portfolio-and-
     * admin-review.md) — only years_of_experience is a real computation today; every other stat
     * depends on a module that doesn't exist yet and is exposed as 0/null rather than fabricated.
     *
     * @return array<string, mixed>
     */
    private function statistics(): array
    {
        return [
            'years_of_experience' => $this->year_established !== null ? max(0, now()->year - (int) $this->year_established) : null,
            'completed_projects' => 0, // blocked on Modules\Projects
            'active_products' => 0, // blocked on Modules\ECommerce
            'average_rating' => null, // blocked on the Reviews sub-area (ECommerce)
            'review_count' => 0, // blocked on the Reviews sub-area (ECommerce)
            'response_rate' => null, // blocked on Core's Chat sub-area
            'response_time' => null, // blocked on Core's Chat sub-area
        ];
    }
}
