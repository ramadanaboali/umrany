<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        });

        // Separate statement: an index add inside the same hasColumn-guarded block above would
        // silently no-op on a re-run if the column already existed but the index didn't.
        if (! $this->indexExists('users', 'users_status_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'mobile',
                'mobile_verified_at',
                'terms_accepted_at',
                'status',
                'last_login_at',
                'last_login_ip',
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
