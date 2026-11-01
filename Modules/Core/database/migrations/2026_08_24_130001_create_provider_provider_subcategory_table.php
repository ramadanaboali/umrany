<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot between providers and provider_subcategories — no SoftDeletes, same exception class
     * as provider_category_provider (root CLAUDE.md Rule 9): a join row has no independent
     * deletion semantics of its own. Unlike categories, a provider may have zero subcategories.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_provider_subcategory')) {
            return;
        }

        Schema::create('provider_provider_subcategory', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_subcategory_id')->constrained()->cascadeOnDelete();

            $table->unique(['provider_id', 'provider_subcategory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_provider_subcategory');
    }
};
