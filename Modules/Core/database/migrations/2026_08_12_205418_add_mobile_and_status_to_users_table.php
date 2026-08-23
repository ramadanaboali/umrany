<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guarded with hasColumn() checks so this is safe to re-run against a
     * database that already has some of these columns (e.g. a partially
     * applied prior deploy) — see docs/architecture/infrastructure.md.
     */
    public function up(): void
    {
        // The root migration declared `email` as NOT NULL — this app registers with mobile OR
        // email (FR-AUTH-001), so a mobile-only account must be able to leave email null.
        // Postgres/MySQL both permit multiple NULLs under a UNIQUE constraint, so nullable+unique
        // together are safe here.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile')->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('users', 'mobile_verified_at')) {
                $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            }

            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'status')) {
                // Plain string + PHP-side backed enum cast (App\Enums\UserStatus) rather than a
                // native DB enum type — keeps this portable and avoids an ALTER TYPE migration
                // dance if a status value is ever added later.
                $table->string('status')->default('active')->after('password');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable();
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }

            if (! Schema::hasColumn('users', 'account_types')) {
                // Captured intent only ("I am a: owner/provider"), never an authorization source —
                // see Modules\Core\Enums\AccountType and docs/decisions/0016-account-type-intent-
                // capture.md. Plain JSON, not a pivot table: a closed, code-defined, small set of
                // values with no need to query "which users chose X" at scale.
                $table->json('account_types')->nullable()->after('status');
            }

            if (! Schema::hasColumn('users', 'suspension_reason')) {
                // Admin-supplied, overwritten on each new suspension — not a history log (no
                // separate audit table, Rule 0: not asked for). Reactivating nulls all three of
                // these out again. See docs/decisions/0027-admin-user-suspend-reactivate.md.
                // `suspended_by_admin_id` is a plain column here (no FK constraint) because this
                // migration runs *before* `admins` is created (timestamp order) — the FK itself is
                // added in create_admins_table.php once that table exists to reference.
                $table->text('suspension_reason')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->unsignedBigInteger('suspended_by_admin_id')->nullable();
            }
        });

        // Separate statement: an index add inside the same hasColumn-guarded block above would
        // silently no-op on a re-run if the column already existed but the index didn't.
        if (! $this->indexExists('users', 'users_status_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('status');
            });
        }

        // Soft-deleting a user must free its email/mobile for someone else to register with —
        // see docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md. The plain
        // unique indexes from the root migration (email) and above (mobile) count soft-deleted
        // rows too, so `Rule::unique(...)->whereNull('deleted_at')` alone would pass FormRequest
        // validation and then hit a raw constraint violation on insert. Replace both with partial
        // indexes that only enforce uniqueness among non-deleted rows.
        if ($this->indexExists('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        }

        if ($this->indexExists('users', 'users_mobile_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_mobile_unique');
            });
        }

        // Both Postgres and SQLite (>=3.8, the test suite's driver per phpunit.xml) support
        // partial indexes with identical `WHERE ... IS NULL` syntax — no driver branch needed
        // here (unlike a partial index keyed on a boolean literal, where the two disagree).
        if (! $this->indexExists('users', 'users_email_active_unique')) {
            DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
        }

        if (! $this->indexExists('users', 'users_mobile_active_unique')) {
            DB::statement('CREATE UNIQUE INDEX users_mobile_active_unique ON users (mobile) WHERE deleted_at IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial indexes and restore the plain unique ones before dropping the columns
        // they reference — order matters, an index drop after dropColumn() would already be moot
        // but doing it first keeps this symmetric with up()'s order.
        DB::statement('DROP INDEX IF EXISTS users_email_active_unique');
        DB::statement('DROP INDEX IF EXISTS users_mobile_active_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email', 'users_email_unique');
            $table->unique('mobile', 'users_mobile_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'mobile',
                'mobile_verified_at',
                'terms_accepted_at',
                'status',
                'account_types',
                'last_login_at',
                'last_login_ip',
                'deleted_at',
                'suspension_reason',
                'suspended_at',
                'suspended_by_admin_id',
            ]);
        });

        // Reverting `email` to NOT NULL is only safe if no mobile-only (null-email) accounts
        // were created while this migration was applied — genuinely destructive otherwise, since
        // it would fail outright against existing null rows rather than silently corrupting data.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $indexName);
    }
};
