<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device previously seen logging in as this user — see Modules\Core\Services\
 * DeviceRecognitionService and docs/decisions/0018-user-device-recognition.md.
 * Deliberately not soft-deletable — an internal recognition log, not a user-manageable list; no
 * delete action exists on it anywhere today.
 */
class UserDevice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
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
