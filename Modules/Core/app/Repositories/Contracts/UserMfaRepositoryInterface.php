<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Models\MfaRecoveryCode;
use Modules\Core\Models\UserMfaSetting;

interface UserMfaRepositoryInterface
{
    public function findSettingForUser(User $user): ?UserMfaSetting;

    /**
     * Replaces any prior (necessarily unconfirmed — a confirmed one is deleted via disable()
     * instead) setting row for this user with a freshly generated secret.
     */
    public function replaceSetting(User $user, string $secret): UserMfaSetting;

    public function confirmSetting(UserMfaSetting $setting): void;

    public function updateLastUsedTimestamp(UserMfaSetting $setting, int $timestamp): void;

    public function deleteSetting(UserMfaSetting $setting): void;

    /**
     * @return Collection<int, MfaRecoveryCode>
     */
    public function unusedRecoveryCodes(User $user): Collection;

    /**
     * @param  array<int, string>  $hashedCodes
     */
    public function replaceRecoveryCodes(User $user, array $hashedCodes): void;

    public function markRecoveryCodeUsed(MfaRecoveryCode $code): void;
}
