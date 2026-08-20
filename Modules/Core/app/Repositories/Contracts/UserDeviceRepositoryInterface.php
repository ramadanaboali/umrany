<?php

declare(strict_types=1);

namespace Modules\Core\Repositories\Contracts;

use App\Models\User;
use Modules\Core\Models\UserDevice;

interface UserDeviceRepositoryInterface
{
    public function findByFingerprint(User $user, string $fingerprint): ?UserDevice;

    public function recordSeen(User $user, string $fingerprint, ?string $deviceName, ?string $ip, ?string $userAgent): UserDevice;

    public function touchSeen(UserDevice $device, ?string $ip): void;
}
