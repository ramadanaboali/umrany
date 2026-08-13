<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Repositories\Contracts\AdminRepositoryInterface;

final class AdminManagementService
{
    public function __construct(
        private readonly AdminRepositoryInterface $admins,
    ) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->admins->paginate($perPage);
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
            'phone' => $data['phone'] ?? null,
        ];

        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        return $this->admins->forceUpdate($admin, $attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Admin $actingAdmin): Admin
    {
        return DB::transaction(function () use ($data, $actingAdmin) {
            $admin = $this->admins->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                // Only a Super Admin may mint another Super Admin — never trust this flag from
                // input alone if the acting admin isn't one themselves (privilege escalation).
                'is_super_admin' => $actingAdmin->is_super_admin && (bool) ($data['is_super_admin'] ?? false),
                'status' => AdminStatus::Active,
            ]);

            $admin->syncRoles($data['roles'] ?? []);

            return $admin;
        });
    }

    /**
     * Three edge cases guarded here, all deliberate — none of them are enforceable purely by a
     * permission check, since a permitted admin editing *another* admin is exactly when these
     * matter:
     *  1. An admin can never suspend their own account through this form (self-lockout).
     *  2. Only a Super Admin may grant/revoke Super Admin status on someone else.
     *  3. The last active Super Admin can never be demoted or suspended — there must always be
     *     at least one way back into the system.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Admin $admin, array $data, Admin $actingAdmin): Admin
    {
        return DB::transaction(function () use ($admin, $data, $actingAdmin) {
            $targetStatus = AdminStatus::from($data['status']);

            if ($admin->is($actingAdmin) && $targetStatus !== AdminStatus::Active) {
                throw ValidationException::withMessages(['status' => ['You cannot suspend your own account.']]);
            }

            $willRevokeSuperAdmin = $admin->is_super_admin && (
                ($actingAdmin->is_super_admin && array_key_exists('is_super_admin', $data) && ! $data['is_super_admin'])
                || $targetStatus !== AdminStatus::Active
            );

            if ($willRevokeSuperAdmin && $this->admins->countOtherActiveSuperAdmins($admin->id) === 0) {
                throw ValidationException::withMessages(['status' => ['This is the last active Super Admin — promote another admin to Super Admin first.']]);
            }

            $attributes = [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'status' => $targetStatus,
            ];

            if ($actingAdmin->is_super_admin && array_key_exists('is_super_admin', $data)) {
                $attributes['is_super_admin'] = (bool) $data['is_super_admin'];
            }

            $this->admins->forceUpdate($admin, $attributes);

            if (array_key_exists('roles', $data)) {
                $admin->syncRoles($data['roles']);
            }

            return $admin->refresh();
        });
    }
}
