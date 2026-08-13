<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Models\Admin;

interface AdminRepositoryInterface
{
    public function findById(int $id): ?Admin;

    public function findByEmail(string $email): ?Admin;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Admin;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forceUpdate(Admin $admin, array $attributes): Admin;

    /**
     * How many *other* active Super Admins exist besides the given one — used to guard against
     * demoting/suspending the last one. See Modules\Core\Services\Admin\AdminManagementService.
     */
    public function countOtherActiveSuperAdmins(int $excludeAdminId): int;

    public function paginate(int $perPage = 20): LengthAwarePaginator;
}
