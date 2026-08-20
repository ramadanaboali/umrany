<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Models\MfaRecoveryCode;
use Modules\Core\Models\UserMfaSetting;
use Modules\Core\Repositories\Contracts\UserMfaRepositoryInterface;

final class EloquentUserMfaRepository implements UserMfaRepositoryInterface
{
    public function findSettingForUser(User $user): ?UserMfaSetting
    {
        return UserMfaSetting::query()->where('user_id', $user->id)->first();
    }

    public function replaceSetting(User $user, string $secret): UserMfaSetting
    {
        UserMfaSetting::query()->where('user_id', $user->id)->delete();

        return UserMfaSetting::query()->create([
            'user_id' => $user->id,
            'secret' => $secret,
        ]);
    }

    public function confirmSetting(UserMfaSetting $setting): void
    {
        $setting->forceFill(['confirmed_at' => now()])->save();
    }

    public function updateLastUsedTimestamp(UserMfaSetting $setting, int $timestamp): void
    {
        $setting->forceFill(['last_used_timestamp' => $timestamp])->save();
    }

    public function deleteSetting(UserMfaSetting $setting): void
    {
        $setting->delete();
    }

    public function unusedRecoveryCodes(User $user): Collection
    {
        return MfaRecoveryCode::query()->where('user_id', $user->id)->unused()->get();
    }

    public function replaceRecoveryCodes(User $user, array $hashedCodes): void
    {
        MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

        foreach ($hashedCodes as $hash) {
            MfaRecoveryCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => $hash,
            ]);
        }
    }

    public function markRecoveryCodeUsed(MfaRecoveryCode $code): void
    {
        $code->forceFill(['used_at' => now()])->save();
    }
}
