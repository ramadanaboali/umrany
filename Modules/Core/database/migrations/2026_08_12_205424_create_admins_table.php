<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins are a deliberately separate identity from `users` — distinct table, distinct
     * `admin` guard (config/auth.php), never reachable through a Sanctum API token. See
     * docs/architecture/admin-portal.md.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email'); // uniqueness enforced below (partial, soft-delete-aware)
                $table->string('password');
                $table->string('phone')->nullable(); // uniqueness enforced below (partial, soft-delete-aware)
                $table->string('avatar_path')->nullable();
                // Drives the admin dashboard's UI language and its LTR/RTL direction via
                // Modules\Core\Enums\Language::direction() — mirrors user_profiles.preferred_language
                // exactly (same enum, same column shape). See
                // docs/decisions/0022-admin-dashboard-en-ar-localization.md.
                $table->string('preferred_language', 2)->default('en');
                // Dark/light mode — mirrors preferred_language's DB-plus-browser persistence pattern.
                // See docs/decisions/0021-velzon-material-admin-theme.md.
                $table->string('theme_mode', 5)->default('light');
                // Bypasses all permission checks (Gate::before) regardless of role assignment —
                // a boolean, not a role name, so renaming/removing a "Super Admin" role can never
                // accidentally revoke the bypass. See docs/modules/core.md § Admin.
                $table->boolean('is_super_admin')->default(false);
                $table->string('status')->default('active'); // active|suspended
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
            });
        }

        // Soft-delete-aware uniqueness — a soft-deleted admin's email/phone must be free for a
        // new admin to reuse, matching the fix in docs/decisions/0014-user-soft-deletes-and-
        // partial-unique-indexes.md (originally applied to `users` only; `admins` already had
        // SoftDeletes but was left on plain unique indexes, blocking exactly this).
        if (! $this->indexExists('admins', 'admins_email_active_unique')) {
            DB::statement('CREATE UNIQUE INDEX admins_email_active_unique ON admins (email) WHERE deleted_at IS NULL');
        }

        if (! $this->indexExists('admins', 'admins_phone_active_unique')) {
            DB::statement('CREATE UNIQUE INDEX admins_phone_active_unique ON admins (phone) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS admins_email_active_unique');
        DB::statement('DROP INDEX IF EXISTS admins_phone_active_unique');
        Schema::dropIfExists('admins');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $indexName);
    }
};
