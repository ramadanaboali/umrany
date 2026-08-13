<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * The platform-wide default country (Saudi Arabia) — looked up by code, not a hardcoded id,
     * since auto-increment ids aren't guaranteed stable across environments. See
     * Modules/Core/config/config.php `defaults.country_code` and
     * Modules/Core/database/seeders/MasterDataSeeder, which guarantees this code always exists.
     */
    public static function default(): ?self
    {
        return static::query()->where('code', config('core.defaults.country_code'))->first();
    }
}
