<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Current-state row per provider (one-to-one), not a full history log — an admin review
     * action overwrites status/notes on the existing row. If a full audit history of every
     * review action is needed later, that's the AuditLog entity (docs/modules/core.md § Admin,
     * not yet implemented) recording each transition, not a redesign of this table.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_verifications')) {
            return;
        }

        Schema::create('provider_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('not_submitted');
            // not_submitted|pending|under_review|approved|rejected|expired|suspended
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_verifications');
    }
};
