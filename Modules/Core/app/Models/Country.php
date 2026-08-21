<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * The platform-wide default country — a real `is_default` flag (only one row may ever be
     * true, enforced by a partial unique index), not a config-code lookup. Seeded from
     * config('core.defaults.country_code') so behavior is unchanged from before this became a DB
     * flag — see docs/decisions/0020-database-backed-platform-defaults.md.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }
}
