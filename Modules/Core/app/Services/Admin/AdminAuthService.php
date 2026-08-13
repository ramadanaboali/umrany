<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Admin;
use Modules\Core\Repositories\Contracts\AdminRepositoryInterface;

final class AdminAuthService
{
    public function __construct(
        private readonly AdminRepositoryInterface $admins,
    ) {}

    /**
     * Deliberately does not call Auth::guard('admin')->attempt() — this resolves and validates
     * the Admin first, so a session is only ever established (by the caller, after this
     * succeeds) for a credential+status pair that has already fully passed.
     */
    public function authenticate(string $email, string $password): Admin
    {
        $admin = $this->admins->findByEmail($email);

        if (! $admin || ! Hash::check($password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $admin->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => ['This account cannot sign in. Contact a Super Admin if you believe this is a mistake.'],
            ]);
        }

        return $admin;
    }

    public function recordLogin(Admin $admin, ?string $ip): void
    {
        $this->admins->forceUpdate($admin, ['last_login_at' => now(), 'last_login_ip' => $ip]);
    }
}
