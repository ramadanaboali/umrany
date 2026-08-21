<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately not soft-deletable — regenerating already hard-deletes and replaces the whole
 * batch; recovering a superseded recovery code would be a security regression, not a feature.
 */
class MfaRecoveryCode extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<MfaRecoveryCode>  $query
     * @return Builder<MfaRecoveryCode>
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
