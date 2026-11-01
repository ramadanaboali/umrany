<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;

#[Fillable([
    'company_name', 'logo_path', 'cover_path', 'social_links', 'description', 'country_id', 'city_id',
    'address', 'commercial_registration_number', 'license_number', 'tax_number',
    'year_established', 'employee_count', 'website',
])]
class Provider extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProviderStatus::class,
            'has_ecommerce_access' => 'boolean',
            'has_erp_access' => 'boolean',
            'social_links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<ProviderDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class);
    }

    /**
     * @return HasOne<ProviderVerification, $this>
     */
    public function verification(): HasOne
    {
        return $this->hasOne(ProviderVerification::class);
    }

    /**
     * FR-PROVIDER-003 — a provider profile may offer multiple business categories at once (see
     * this module's CLAUDE.md gotcha: "one provider profile" is not "one service line").
     *
     * @return BelongsToMany<ProviderCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProviderCategory::class, 'provider_category_provider');
    }

    /**
     * FR-PROVIDER-003 — always optional, unlike categories. See
     * docs/decisions/0032-provider-subcategories.md.
     *
     * @return BelongsToMany<ProviderSubcategory, $this>
     */
    public function subcategories(): BelongsToMany
    {
        return $this->belongsToMany(ProviderSubcategory::class, 'provider_provider_subcategory');
    }

    /**
     * @return HasMany<ProviderPortfolio, $this>
     */
    public function portfolios(): HasMany
    {
        return $this->hasMany(ProviderPortfolio::class);
    }

    /**
     * @return HasMany<ProviderCertificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(ProviderCertificate::class);
    }

    /**
     * FR-PROVIDER-004 — a history log, not a current-state row; a provider may retry a check. See
     * Modules\Core\Services\GovernmentVerificationService.
     *
     * @return HasMany<GovernmentVerificationRequest, $this>
     */
    public function governmentVerificationRequests(): HasMany
    {
        return $this->hasMany(GovernmentVerificationRequest::class);
    }

    /**
     * Eager-loadable (unlike a plain ->latest()->first() query) via Eloquent's ofMany() — see
     * ProviderResource's use of whenLoaded('latestGovernmentVerificationRequest').
     *
     * @return HasOne<GovernmentVerificationRequest, $this>
     */
    public function latestGovernmentVerificationRequest(): HasOne
    {
        return $this->hasOne(GovernmentVerificationRequest::class)->latestOfMany('requested_at');
    }

    /**
     * A provider is only actually public when BOTH the profile's own status is Published AND
     * its verification has been Approved — never inferred from one field alone (see the
     * migration comment on `providers.status`).
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === ProviderStatus::Published
            && $this->verification?->status === ProviderVerificationStatus::Approved;
    }
}
