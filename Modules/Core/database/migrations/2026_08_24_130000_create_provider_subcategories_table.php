<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PROVIDER-003: bilingual subcategory taxonomy nested under a ProviderCategory —
     * previously deferred (docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md)
     * for lack of content to seed; now built with a real (if synthetic) starter set — see
     * docs/decisions/0032-provider-subcategories.md. A provider selecting subcategories is always
     * optional, unlike categories.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_subcategories')) {
            return;
        }

        Schema::create('provider_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_category_id')->constrained()->cascadeOnDelete();
            // uniqueness enforced below (partial, soft-delete-aware, scoped to the category)
            $table->string('slug');
            $table->string('name_en');
            $table->string('name_ar');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Soft-delete-aware uniqueness, scoped per category (the same slug may reasonably repeat
        // under two different categories) — see docs/decisions/0014-user-soft-deletes-and-
        // partial-unique-indexes.md.
        DB::statement('CREATE UNIQUE INDEX provider_subcategories_category_slug_active_unique ON provider_subcategories (provider_category_id, slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS provider_subcategories_category_slug_active_unique');
        Schema::dropIfExists('provider_subcategories');
    }
};
