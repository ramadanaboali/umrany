<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PROVIDER-003: bilingual business-category taxonomy for providers. No
     * ProviderSubcategory table in this phase — see docs/decisions/0030-provider-taxonomy-
     * portfolio-and-admin-review.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_categories')) {
            return;
        }

        Schema::create('provider_categories', function (Blueprint $table) {
            $table->id();
            // uniqueness enforced below (partial, soft-delete-aware)
            $table->string('slug');
            $table->string('name_en');
            $table->string('name_ar');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Soft-delete-aware uniqueness — see docs/decisions/0014-user-soft-deletes-and-partial-
        // unique-indexes.md.
        DB::statement('CREATE UNIQUE INDEX provider_categories_slug_active_unique ON provider_categories (slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS provider_categories_slug_active_unique');
        Schema::dropIfExists('provider_categories');
    }
};
