<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Repositories\Contracts\UserDeviceRepositoryInterface;

/**
 * Backs "login from a new device" detection — a real `user_devices` table, not inferred from
 * Sanctum tokens: tokens are deleted on logout/password-reset and carry no IP/user-agent, so
 * token history alone would false-positive every post-logout login as "new device". See
 * docs/decisions/0018-user-device-recognition.md.
 */
final class DeviceRecognitionService
{
    public function __construct(
        private readonly UserDeviceRepositoryInterface $devices,
    ) {}

    /**
     * Records this login's device and returns true only the first time this fingerprint is seen
     * for this user — call exactly once per completed login (Modules\Core\Services\
     * AuthService::issueAuthenticatedSession()).
     */
    public function recognize(User $user, ?string $deviceName, ?string $ip, ?string $userAgent): bool
    {
        $fingerprint = hash('sha256', ($userAgent ?? '').'|'.($deviceName ?? ''));

        $existing = $this->devices->findByFingerprint($user, $fingerprint);

        if ($existing !== null) {
            $this->devices->touchSeen($existing, $ip);

            return false;
        }

        $this->devices->recordSeen($user, $fingerprint, $deviceName, $ip, $userAgent);

        return true;
    }
}
