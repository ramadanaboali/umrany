<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use App\Models\User;
use Modules\Core\Models\UserDevice;
use Modules\Core\Repositories\Contracts\UserDeviceRepositoryInterface;

final class EloquentUserDeviceRepository implements UserDeviceRepositoryInterface
{
    public function findByFingerprint(User $user, string $fingerprint): ?UserDevice
    {
        return UserDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();
    }

    public function recordSeen(User $user, string $fingerprint, ?string $deviceName, ?string $ip, ?string $userAgent): UserDevice
    {
        return UserDevice::query()->create([
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'device_name' => $deviceName,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function touchSeen(UserDevice $device, ?string $ip): void
    {
        $device->forceFill(['last_seen_at' => now(), 'ip_address' => $ip ?? $device->ip_address])->save();
    }
}
