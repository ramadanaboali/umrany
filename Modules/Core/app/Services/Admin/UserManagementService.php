<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;

/**
 * Deliberately minimal — read-only listing plus session revocation, not a full user-management
 * CRUD screen. End users self-register and self-delete their own accounts (see
 * docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md); there is no admin
 * "create"/"delete" action to build here. See docs/architecture/admin-portal.md.
 */
final class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function paginate(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->users->paginateForAdmin($perPage, $search);
    }

    public function revokeAllSessions(User $user): int
    {
        return $user->tokens()->delete();
    }
}
