<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single-row config table (id=1, guaranteed by SiteSettingSeeder — see
     * Modules\Core\Models\SiteSetting::current()), not a key-value store — the requested fields
     * are fixed and small, so plain columns follow root CLAUDE.md Rule 3 ("don't build
     * abstractions the current task doesn't need") better than a generic settings engine would.
     *
     * Deliberately named `SiteSetting`, not `Setting`/`SystemSetting` — Modules/Core/CLAUDE.md
     * documents a future, broader `SystemSetting` entity (commission %, gateway fees, moderation
     * mode — Phase 7 business config, not built). This table is scoped to branding/contact/social
     * only and must not be confused with that future entity.
     */
    public function up(): void
    {
        if (Schema::hasTable('site_settings')) {
            return;
        }

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name');
            // Browser-tab / SEO title — distinct from site_name (the brand name shown in the UI).
            $table->string('site_title')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();
            // Keyed by Modules\Core\Enums\SocialPlatform — the same closed platform set already
            // used by Provider::social_links; unknown keys are rejected at validation.
            $table->json('social_links')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
