<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FR-PROVIDER-003 business-category taxonomy — bilingual master data, same shape as Country
 * (name_en/name_ar, both returned by the API, no server-side locale resolution). Subcategories
 * (ProviderSubcategory) nest under a category — see docs/decisions/0032-provider-subcategories.md.
 */
class ProviderCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return BelongsToMany<Provider, $this>
     */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_category_provider');
    }

    /**
     * @return HasMany<ProviderSubcategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(ProviderSubcategory::class);
    }
}
