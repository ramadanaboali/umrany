<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('code', 2)->unique(); // ISO 3166-1 alpha-2
                $table->string('name_en');
                $table->string('name_ar');
                $table->string('phone_code', 8)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }

        // The platform-wide default (previously a config('core.defaults.country_code') lookup —
        // see Country::default()) is now a real DB flag, so it can eventually be admin-managed
        // instead of redeploy-managed. See docs/decisions/0020-database-backed-platform-defaults.md.
        if (! Schema::hasColumn('countries', 'is_default')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        // Only one row may ever be the default — enforced at the DB level, not just by seeder
        // discipline. Postgres/SQLite disagree on partial-index boolean-literal syntax (unlike a
        // `WHERE ... IS NULL` clause), so this one genuinely needs the driver branch.
        if (! $this->indexExists('countries', 'countries_single_default')) {
            DB::statement(DB::getDriverName() === 'pgsql'
                ? 'CREATE UNIQUE INDEX countries_single_default ON countries ((is_default)) WHERE is_default'
                : 'CREATE UNIQUE INDEX countries_single_default ON countries (is_default) WHERE is_default = 1');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS countries_single_default');
        Schema::dropIfExists('countries');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $indexName);
    }
};
