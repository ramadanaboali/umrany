<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
