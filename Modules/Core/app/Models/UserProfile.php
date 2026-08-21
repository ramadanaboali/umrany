<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Enums\Language;

/**
 * Deliberately not soft-deletable — a 1:1 dependent record owned by `User` with no independent
 * deletion semantics of its own; removal happens only via the parent User's own soft-delete.
 */
#[Fillable(['full_name', 'avatar_path', 'country_id', 'city_id', 'address', 'preferred_language', 'preferred_currency_id'])]
class UserProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'preferred_language' => Language::class,
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
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'preferred_currency_id');
    }
}
