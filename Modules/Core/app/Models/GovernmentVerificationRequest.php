<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Enums\GovernmentVerificationStatus;

class GovernmentVerificationRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => GovernmentVerificationStatus::class,
            'imported_data' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'applied_at' => 'datetime',
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
     * @return BelongsTo<Admin, $this>
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'applied_by_admin_id');
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
