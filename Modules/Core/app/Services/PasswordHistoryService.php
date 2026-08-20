<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Repositories\Contracts\PasswordHistoryRepositoryInterface;

/**
 * Shared by both `App\Models\User` and `Modules\Core\Models\Admin` — the underlying table is a
 * single morph, see Modules\Core\Models\PasswordHistory. history_count = 0 disables the reuse
 * check entirely (still records history either way, so re-enabling later has real data to check
 * against). See docs/decisions/0013-config-driven-password-policy-and-history.md.
 */
final class PasswordHistoryService
{
    public function __construct(
        private readonly PasswordHistoryRepositoryInterface $history,
    ) {}

    /**
     * Checks the candidate plain-text password against the subject's current password (if any)
     * plus its last `history_count` recorded hashes.
     */
    public function isReused(Model $subject, string $plainPassword): bool
    {
        $historyCount = (int) config('core.password_policy.history_count');

        if ($historyCount <= 0) {
            return false;
        }

        $currentHash = $subject->getAttribute('password');

        if (is_string($currentHash) && $currentHash !== '' && Hash::check($plainPassword, $currentHash)) {
            return true;
        }

        foreach ($this->history->recentHashes($subject, $historyCount) as $hash) {
            if (Hash::check($plainPassword, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function record(Model $subject, string $hashedPassword): void
    {
        $this->history->record($subject, $hashedPassword);
    }
}
