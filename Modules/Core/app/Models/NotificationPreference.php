<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Enums\NotificationEvent;

/**
 * Deliberately not soft-deletable — a per-user-per-event settings row, always updated in place
 * (see NotificationPreferenceService::update()'s updateOrCreate), never listed or removed.
 */
class NotificationPreference extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_type' => NotificationEvent::class,
            'in_app' => 'boolean',
            'email' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
