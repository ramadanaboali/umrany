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

        // users.suspended_by_admin_id (added in create_users_table's own migration, earlier in
        // timestamp order — before `admins` existed to reference) gets its FK constraint here,
        // the first point `admins` actually exists. See docs/decisions/0027-admin-user-suspend-
        // reactivate.md.
        if (! $this->foreignKeyExists('users', 'users_suspended_by_admin_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('suspended_by_admin_id', 'users_suspended_by_admin_id_foreign')
                    ->references('id')->on('admins')->nullOnDelete();
            });

            // SQLite has no native ALTER TABLE ADD CONSTRAINT — adding a foreign key to an
            // existing table makes Laravel's SQLite grammar rebuild the whole `users` table
            // (copy-then-swap), and that rebuild only recreates indexes it tracks via Blueprint,
            // silently downgrading the raw partial unique indexes created in create_users_table's
            // migration (`WHERE deleted_at IS NULL`) to plain ones — confirmed directly by
            // inspecting `sqlite_master` after this ran. Postgres has no such rebuild, so this is
            // a no-op there; re-asserting them here (drop + recreate, not just an existence guard,
            // since the plain-downgraded index already exists under the same name) repairs it on
            // SQLite and is harmless everywhere else.
            DB::statement('DROP INDEX IF EXISTS users_email_active_unique');
            DB::statement('DROP INDEX IF EXISTS users_mobile_active_unique');
            DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX users_mobile_active_unique ON users (mobile) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        // Drop the cross-table FK before dropping `admins` — otherwise the database refuses to
        // drop a table another table's constraint still references.
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_suspended_by_admin_id_foreign');
        });

        DB::statement('DROP INDEX IF EXISTS admins_email_active_unique');
        DB::statement('DROP INDEX IF EXISTS admins_phone_active_unique');
        Schema::dropIfExists('admins');
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn (array $fk) => $fk['name'] === $constraintName);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $indexName);
    }
};
