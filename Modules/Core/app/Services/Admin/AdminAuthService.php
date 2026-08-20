<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Admin;
use Modules\Core\Repositories\Contracts\AdminRepositoryInterface;
use Modules\Core\Services\PasswordHistoryService;

final class AdminAuthService
{
    public function __construct(
        private readonly AdminRepositoryInterface $admins,
        private readonly PasswordHistoryService $passwordHistory,
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
                'email' => [__('core::admin.invalid_credentials')],
            ]);
        }

        if (! $admin->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => [__('core::admin.account_cannot_sign_in')],
            ]);
        }

        return $admin;
    }

    public function recordLogin(Admin $admin, ?string $ip): void
    {
        $this->admins->forceUpdate($admin, ['last_login_at' => now(), 'last_login_ip' => $ip]);
    }

    /**
     * Called from the Password broker's reset() callback (app/Http/Controllers/Admin/
     * PasswordResetController) — the broker has already verified the token+email pair by the
     * time this runs, which is this flow's equivalent of the end-user side's "only after OTP
     * consumption" placement for the reuse check. See Modules\Core\Rules\NotAPreviousPassword.
     */
    public function resetPassword(Admin $admin, string $newPassword): void
    {
        if ($this->passwordHistory->isReused($admin, $newPassword)) {
            throw ValidationException::withMessages([
                'password' => [__('core::admin.password_previously_used')],
            ]);
        }

        $this->admins->forceUpdate($admin, ['password' => $newPassword]);
        $this->passwordHistory->record($admin, $admin->password);
    }
}
