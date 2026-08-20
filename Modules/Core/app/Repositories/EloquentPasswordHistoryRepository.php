<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Models\PasswordHistory;
use Modules\Core\Repositories\Contracts\PasswordHistoryRepositoryInterface;

final class EloquentPasswordHistoryRepository implements PasswordHistoryRepositoryInterface
{
    public function recentHashes(Model $subject, int $limit): Collection
    {
        return PasswordHistory::query()
            ->where('authenticatable_type', $subject->getMorphClass())
            ->where('authenticatable_id', $subject->getKey())
            ->latest('created_at')
            ->limit($limit)
            ->pluck('password_hash');
    }

    public function record(Model $subject, string $passwordHash): void
    {
        PasswordHistory::query()->create([
            'authenticatable_type' => $subject->getMorphClass(),
            'authenticatable_id' => $subject->getKey(),
            'password_hash' => $passwordHash,
        ]);
    }
}
