<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FR-PROVIDER-006 past-project showcase item. `images` (uploaded file paths on the `public`
 * disk) and `videos` (plain URLs, not uploads) are both JSON arrays — see
 * docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md.
 */
class ProviderPortfolio extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'videos' => 'array',
            'completion_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<ProviderCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProviderCategory::class, 'provider_category_id');
    }
}
