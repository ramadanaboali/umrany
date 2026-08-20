<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (user, event type), with a boolean column per channel — not a row per
     * (user, event, channel). With only two channels today this halves row count and makes both
     * "fetch the whole matrix" and "update the whole matrix" a single query/upsert; the taller
     * shape only pays off once channels become numerous or admin-configurable, neither of which
     * is true here (push is explicitly deferred, see Modules\Core\Enums\NotificationChannel). A
     * user with no row for an event gets Modules\Core\Enums\NotificationEvent::defaultChannels()
     * — no backfill migration needed, no row written until they actually change something. See
     * docs/decisions/0017-notification-preferences-schema.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            return;
        }

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
