<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
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
     * The platform-wide default currency (SAR) — see Country::default() for why this is a code
     * lookup, not a hardcoded id.
     */
    public static function default(): ?self
    {
        return static::query()->where('code', config('core.defaults.currency_code'))->first();
    }
}
