<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface PasswordHistoryRepositoryInterface
{
    /**
     * The most recent $limit password hashes for this subject, newest first.
     *
     * @return Collection<int, string>
     */
    public function recentHashes(Model $subject, int $limit): Collection;

    public function record(Model $subject, string $passwordHash): void;
}
