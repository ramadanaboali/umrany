<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FR-PROVIDER-003 subcategory taxonomy, nested under a ProviderCategory — bilingual master data,
 * same shape as ProviderCategory. Selecting subcategories is always optional for a provider,
 * unlike categories. See docs/decisions/0032-provider-subcategories.md.
 */
class ProviderSubcategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<ProviderCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProviderCategory::class, 'provider_category_id');
    }

    /**
     * @return BelongsToMany<Provider, $this>
     */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_provider_subcategory');
    }
}
