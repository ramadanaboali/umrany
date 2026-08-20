<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    /**
     * Admin dashboard listing — see app/Http/Controllers/Admin/UserController.
     */
    public function paginateForAdmin(int $perPage, ?string $search): LengthAwarePaginator;

    /**
     * Resolves either identifier the platform accepts at login/reset (FR-AUTH-001/002).
     */
    public function findByLoginIdentifier(string $login): ?User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forceUpdate(User $user, array $attributes): User;

    /**
     * Soft-deletes the account — frees its email/mobile for reuse (see the partial unique
     * indexes added by Modules/Core/database/migrations/2026_08_12_205418_...) while preserving
     * the row itself for historical/audit purposes.
     */
    public function softDelete(User $user): void;
}
