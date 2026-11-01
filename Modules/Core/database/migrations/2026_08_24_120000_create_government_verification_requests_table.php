<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PROVIDER-004: a history log (not a current-state row like provider_verifications) — a
     * provider may retry a check (e.g. after "service unavailable"), and every attempt is kept.
     * `imported_data` is stored as-received and never applied to the `providers` row
     * automatically — see Modules\Core\Services\Admin\ProviderManagementService::
     * applyGovernmentVerification() and the "never silently overwrite user-entered data" rule in
     * Modules/Core/CLAUDE.md's Gotchas.
     */
    public function up(): void
    {
        if (Schema::hasTable('government_verification_requests')) {
            return;
        }

        Schema::create('government_verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('commercial_registration_number');
            $table->string('status')->default('pending');
            // pending|completed|failed
            $table->string('source')->default('mock');
            $table->json('imported_data')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('applied_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_verification_requests');
    }
};
