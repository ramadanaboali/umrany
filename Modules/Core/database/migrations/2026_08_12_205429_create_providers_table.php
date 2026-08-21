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
        if (! Schema::hasTable('providers')) {
            Schema::create('providers', function (Blueprint $table) {
                $table->id();
                // One provider profile per account — enforced at the DB level, not just app logic
                // (docs/modules/core.md § Provider: "one user account owns at most one provider
                // profile"). Uniqueness itself is enforced below (partial, soft-delete-aware) so a
                // soft-deleted provider's former owner can activate a new one.
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('company_name');
                $table->string('logo_path')->nullable();
                $table->string('cover_path')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
                $table->string('address')->nullable();
                // uniqueness enforced below (partial, soft-delete-aware)
                $table->string('commercial_registration_number')->nullable();
                $table->string('license_number')->nullable();
                $table->string('tax_number')->nullable();
                $table->unsignedSmallInteger('year_established')->nullable();
                $table->unsignedInteger('employee_count')->nullable();
                $table->string('website')->nullable();
                // Public-visibility state, distinct from provider_verifications.status (the document
                // review workflow) — a provider can be `pending_review` here while its verification
                // document review is `under_review` there; publishing requires both to align.
                $table->string('status')->default('pending_review'); // pending_review|published|suspended

                // Denormalized read-cache of entitlement, kept false until Modules/Core's Subscription
                // sub-area lands (roadmap Phase 2) and starts syncing these via domain events on
                // SubscriptionActivated/SubscriptionExpired. Never authoritative for request-time
                // gating — Modules\Core\Contracts\ModuleEntitlementChecker remains the source of
                // truth for that; these columns exist only so admin/report list queries don't need
                // to join subscription tables that don't exist yet. See
                // docs/architecture/module-boundaries.md § User capability resolution.
                $table->boolean('has_ecommerce_access')->default(false);
                $table->boolean('has_erp_access')->default(false);

                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
            });
        }

        // Soft-delete-aware uniqueness — see docs/decisions/0014-user-soft-deletes-and-partial-
        // unique-indexes.md. `providers` already had SoftDeletes but was left on plain unique
        // indexes, blocking a soft-deleted provider's former owner/CR-number from ever being
        // reused.
        if (! $this->indexExists('providers', 'providers_user_id_active_unique')) {
            DB::statement('CREATE UNIQUE INDEX providers_user_id_active_unique ON providers (user_id) WHERE deleted_at IS NULL');
        }

        if (! $this->indexExists('providers', 'providers_commercial_registration_number_active_unique')) {
            DB::statement('CREATE UNIQUE INDEX providers_commercial_registration_number_active_unique ON providers (commercial_registration_number) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS providers_user_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS providers_commercial_registration_number_active_unique');
        Schema::dropIfExists('providers');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $indexName);
    }
};
