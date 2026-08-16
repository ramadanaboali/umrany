<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Events\AdminPermissionsChanged;
use Modules\Core\Models\Admin;
use Modules\Core\Repositories\Contracts\AdminRepositoryInterface;
use Modules\Core\Support\PhoneNumber;

final class AdminManagementService
{
    public function __construct(
        private readonly AdminRepositoryInterface $admins,
    ) {}

    /**
     * Admins visible in the dashboard listing — always excludes Super Admins, see
     * Admin::excludingSuperAdmins() and docs/architecture/admin-portal.md.
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->admins->paginate($perPage);
    }

    public function countRegular(): int
    {
        return $this->admins->countRegular();
    }

    /**
     * Self-service profile edit (name/phone/optional password) — deliberately separate from
     * update() above: no self-suspend/last-super-admin/privilege-escalation guards apply here,
     * since an admin editing their own name/phone/password isn't touching status or role.
     *
     * @param  array{name: string, phone: ?string, password?: string}  $data
     */
    public function updateOwnProfile(Admin $admin, array $data): Admin
    {
        $attributes = [
            'name' => $data['name'],
            'phone' => PhoneNumber::normalize($data['phone'] ?? null),
        ];

        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        return $this->admins->forceUpdate($admin, $attributes);
    }

    /**
     * is_super_admin is never set here — it can only be granted via the
     * `core:admin:promote-super` console command, never through the web UI/API, so every admin
     * created through this path starts as a regular (non-super) admin.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Admin
    {
        return DB::transaction(function () use ($data) {
            $admin = $this->admins->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => PhoneNumber::normalize($data['phone'] ?? null),
                'password' => $data['password'],
                'status' => AdminStatus::Active,
            ]);

            $admin->syncRoles($data['roles'] ?? []);

            return $admin;
        });
    }

    /**
     * Two edge cases guarded here, both deliberate — neither is enforceable purely by a
     * permission check, since a permitted admin editing *another* admin is exactly when these
     * matter:
     *  1. An admin can never suspend their own account through this form (self-lockout).
     *  2. The last active Super Admin can never be suspended — there must always be at least one
     *     way back into the system. (Super Admin status itself is no longer settable through this
     *     method at all — see `create()` and `core:admin:promote-super`.)
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Admin $admin, array $data, Admin $actingAdmin): Admin
    {
        $originalStatus = $admin->status;
        $originalRoleNames = $admin->roles->pluck('name')->sort()->values()->all();

        $admin = DB::transaction(function () use ($admin, $data, $actingAdmin) {
            $targetStatus = AdminStatus::from($data['status']);

            if ($admin->is($actingAdmin) && $targetStatus !== AdminStatus::Active) {
                throw ValidationException::withMessages(['status' => ['You cannot suspend your own account.']]);
            }

            if ($admin->is_super_admin && $targetStatus !== AdminStatus::Active
                && $this->admins->countOtherActiveSuperAdmins($admin->id) === 0) {
                throw ValidationException::withMessages(['status' => ['This is the last active Super Admin — promote another admin to Super Admin first.']]);
            }

            $this->admins->forceUpdate($admin, [
                'name' => $data['name'],
                'phone' => PhoneNumber::normalize($data['phone'] ?? null),
                'status' => $targetStatus,
            ]);

            if (array_key_exists('roles', $data)) {
                $admin->syncRoles($data['roles']);
            }

            return $admin->refresh();
        });

        // Only tell the target admin's session (if any) to refresh when access actually changed —
        // a plain name/phone edit isn't worth a live banner. See docs/decisions/0008-admin-rbac-
        // live-refresh-via-reverb.md.
        $newRoleNames = $admin->roles()->pluck('name')->sort()->values()->all();
        if ($admin->status !== $originalStatus || $newRoleNames !== $originalRoleNames) {
            AdminPermissionsChanged::dispatch($admin->id);
        }

        return $admin;
    }

    /**
     * Guards mirror update()'s self-lockout and last-Super-Admin protections.
     */
    public function delete(Admin $admin, Admin $actingAdmin): void
    {
        if ($admin->is($actingAdmin)) {
            throw ValidationException::withMessages(['admin' => ['You cannot delete your own account.']]);
        }

        if ($admin->is_super_admin && $this->admins->countOtherActiveSuperAdmins($admin->id) === 0) {
            throw ValidationException::withMessages(['admin' => ['This is the last active Super Admin — promote another admin to Super Admin first.']]);
        }

        $this->admins->delete($admin);
    }
}
