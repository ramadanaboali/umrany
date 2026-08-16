<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Enums\AdminStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Repositories\Contracts\AdminRepositoryInterface;

final class EloquentAdminRepository implements AdminRepositoryInterface
{
    public function findById(int $id): ?Admin
    {
        return Admin::query()->find($id);
    }

    public function findByEmail(string $email): ?Admin
    {
        return Admin::query()->where('email', $email)->first();
    }

    public function create(array $attributes): Admin
    {
        // forceCreate: `is_super_admin`/`status` are deliberately excluded from Admin's
        // #[Fillable(...)] — see docs/architecture/backend-layering.md.
        return Admin::forceCreate($attributes);
    }

    public function forceUpdate(Admin $admin, array $attributes): Admin
    {
        $admin->forceFill($attributes)->save();

        return $admin;
    }

    public function countOtherActiveSuperAdmins(int $excludeAdminId): int
    {
        return Admin::query()
            ->where('is_super_admin', true)
            ->where('status', AdminStatus::Active->value)
            ->where('id', '!=', $excludeAdminId)
            ->count();
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Admin::query()->excludingSuperAdmins()->with('roles')->orderBy('name')->paginate($perPage);
    }

    public function countRegular(): int
    {
        return Admin::query()->excludingSuperAdmins()->count();
    }

    public function delete(Admin $admin): void
    {
        $admin->delete();
    }
}
