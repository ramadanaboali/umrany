<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PROVIDER-002: "Social Media Links" is part of a provider's supported company
     * information. Stored as a JSON object keyed by platform (Modules\Core\Enums\SocialPlatform)
     * rather than a fixed set of columns — the PDF doesn't fix the platform list, and this keeps
     * adding a platform later a code-only change (extend the enum), not a migration.
     */
    public function up(): void
    {
        if (Schema::hasColumn('providers', 'social_links')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
