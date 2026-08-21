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
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3); // ISO 4217 — uniqueness enforced below (partial, soft-delete-aware)
                $table->string('name_en');
                $table->string('name_ar');
                $table->string('symbol', 8);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('is_active');
            });
        }

        // See the countries migration's identical addition for the full rationale —
        // docs/decisions/0020-database-backed-platform-defaults.md.
        if (! Schema::hasColumn('currencies', 'is_default')) {
            Schema::table('currencies', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        if (! $this->indexExists('currencies', 'currencies_single_default')) {
            DB::statement(DB::getDriverName() === 'pgsql'
                ? 'CREATE UNIQUE INDEX currencies_single_default ON currencies ((is_default)) WHERE is_default'
                : 'CREATE UNIQUE INDEX currencies_single_default ON currencies (is_default) WHERE is_default = 1');
        }

        // Soft-delete-aware uniqueness on `code` — see the countries migration's identical
        // addition; docs/decisions/0014-user-soft-deletes-and-partial-unique-indexes.md.
        if (! $this->indexExists('currencies', 'currencies_code_active_unique')) {
            DB::statement('CREATE UNIQUE INDEX currencies_code_active_unique ON currencies (code) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS currencies_code_active_unique');
        DB::statement('DROP INDEX IF EXISTS currencies_single_default');
        Schema::dropIfExists('currencies');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $indexName);
    }
};
