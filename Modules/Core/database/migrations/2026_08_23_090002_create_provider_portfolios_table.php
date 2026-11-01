<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PROVIDER-006: past-project showcase items. `images` is a JSON array of `public`-disk
     * paths (uploaded files, same storage convention as logo/cover); `videos` is a JSON array of
     * plain URLs, not uploads — see docs/decisions/0030-provider-taxonomy-portfolio-and-admin-
     * review.md for why the two are split.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_portfolios')) {
            return;
        }

        Schema::create('provider_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_category_id')->nullable()->constrained('provider_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->json('videos')->nullable();
            $table->date('completion_date')->nullable();
            $table->string('project_location')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_portfolios');
    }
};
