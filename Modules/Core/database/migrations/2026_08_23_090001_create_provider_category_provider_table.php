<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot between providers and provider_categories — no SoftDeletes, same exception class as
     * UserProfile (root CLAUDE.md Rule 9): a join row has no independent deletion semantics of
     * its own, it just goes away when either side is detached/deleted.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_category_provider')) {
            return;
        }

        Schema::create('provider_category_provider', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_category_id')->constrained()->cascadeOnDelete();

            $table->unique(['provider_id', 'provider_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_category_provider');
    }
};
