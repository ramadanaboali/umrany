<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Core\Events\AdminPermissionsChanged;
use Modules\Core\Repositories\Contracts\AdminRepositoryInterface;

/**
 * The only way to grant/revoke is_super_admin outside the AdminSeeder bootstrap account — the web
 * dashboard never accepts this field from anyone, including another Super Admin (see
 * docs/architecture/admin-portal.md). Deliberately a plain `Log::info()` call, not a new
 * `AuditLog` model/table — that entity isn't built yet (Modules/Core/CLAUDE.md "Implementation
 * status") and this single command isn't reason enough to build it speculatively (root CLAUDE.md
 * Rule 0).
 */
final class PromoteSuperAdminCommand extends Command
{
    protected $signature = 'core:admin:promote-super
        {email : Email of the admin to promote/revoke}
        {--revoke : Revoke Super Admin status instead of granting it}';

    protected $description = 'Grant or revoke Super Admin status on an admin account — the only way to change is_super_admin.';

    public function __construct(
        private readonly AdminRepositoryInterface $admins,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $admin = $this->admins->findByEmail($email);

        if (! $admin) {
            $this->error("No admin found with email \"{$email}\".");

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');

        if (! $revoke) {
            if ($admin->is_super_admin) {
                $this->info("\"{$email}\" is already a Super Admin.");

                return self::SUCCESS;
            }

            $this->admins->forceUpdate($admin, ['is_super_admin' => true]);
            Log::info('Super Admin status granted via console', ['admin_id' => $admin->id, 'email' => $email]);
            $this->info("Granted Super Admin status to \"{$email}\".");
        } else {
            if (! $admin->is_super_admin) {
                $this->info("\"{$email}\" is not a Super Admin.");

                return self::SUCCESS;
            }

            if ($this->admins->countOtherActiveSuperAdmins($admin->id) === 0) {
                $this->error("Refusing to revoke: \"{$email}\" is the last active Super Admin. Promote another admin first.");

                return self::FAILURE;
            }

            $this->admins->forceUpdate($admin, ['is_super_admin' => false]);
            Log::info('Super Admin status revoked via console', ['admin_id' => $admin->id, 'email' => $email]);
            $this->info("Revoked Super Admin status from \"{$email}\".");
        }

        AdminPermissionsChanged::dispatch($admin->id);

        return self::SUCCESS;
    }
}
