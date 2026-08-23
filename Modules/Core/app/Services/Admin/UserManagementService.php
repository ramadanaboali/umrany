<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Models\Admin;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Services\NotificationDispatchService;

/**
 * Listing, session revocation, suspend/reactivate (with a reason), and deletion — end users
 * self-register, but an admin can now suspend/reactivate/delete a user's account. There is still
 * no admin "create a user" action (end users self-register), see docs/architecture/admin-portal.md
 * and docs/decisions/0027-admin-user-suspend-reactivate.md.
 */
final class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly NotificationDispatchService $notifications,
    ) {}

    public function paginate(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->users->paginateForAdmin($perPage, $search);
    }

    public function revokeAllSessions(User $user): int
    {
        return $user->tokens()->delete();
    }

    /**
     * Immediately revokes the account's sessions too — a just-suspended user shouldn't keep
     * working with an already-issued token until it naturally expires, matching how a password
     * reset/change already revokes sessions elsewhere in this codebase.
     */
    public function suspend(User $user, Admin $actor, string $reason): void
    {
        $user->tokens()->delete();

        $this->users->forceUpdate($user, [
            'status' => UserStatus::Suspended,
            'suspension_reason' => $reason,
            'suspended_at' => now(),
            'suspended_by_admin_id' => $actor->id,
        ]);

        $this->notifications->send($user, NotificationEvent::AccountSuspended, ['reason' => $reason]);
    }

    public function reactivate(User $user): void
    {
        $this->users->forceUpdate($user, [
            'status' => UserStatus::Active,
            'suspension_reason' => null,
            'suspended_at' => null,
            'suspended_by_admin_id' => null,
        ]);

        $this->notifications->send($user, NotificationEvent::AccountActivated);
    }

    /**
     * Admin-initiated equivalent of the end-user's self-service DELETE /auth/account — no
     * password confirmation needed here, since the admin's own permission check is the gate.
     */
    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $this->users->softDelete($user);
    }
}
