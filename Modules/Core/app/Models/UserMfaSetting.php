<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 with User — see docs/decisions/0012-totp-mfa-with-two-step-login.md for why this is a
 * dedicated table rather than columns on `users`, and why `secret` is `encrypted` (reversible)
 * rather than hashed: TOTP verification needs the plaintext back.
 */
class UserMfaSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'confirmed_at' => 'datetime',
            'last_used_timestamp' => 'integer',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
