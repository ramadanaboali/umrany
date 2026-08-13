<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Enums\VerificationCodePurpose;
use Modules\Core\Enums\VerificationCodeType;

class VerificationCode extends Model
{
    use HasFactory;

    /**
     * The plaintext code is never persisted — only its hash. Never mass-assigned; always
     * created explicitly by Modules\Core\Actions\Auth\IssueVerificationCode.
     */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => VerificationCodeType::class,
            'purpose' => VerificationCodePurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }

    /**
     * Codes newer than a fixed guess budget are rejected outright (see IssueVerificationCode's
     * MAX_ATTEMPTS) — this scope is how ConsumeVerificationCode finds the current live code for
     * a user+type without a table scan on every request.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', Carbon::now());
    }
}
