<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('providers')) {
            return;
        }

        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            // One provider profile per account — enforced at the DB level, not just app logic
            // (docs/modules/core.md § Provider: "one user account owns at most one provider profile").
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('commercial_registration_number')->nullable()->unique();
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

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
