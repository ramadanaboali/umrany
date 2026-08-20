<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per recovery code, hashed at rest (bcrypt via Hash::make — same convention as
     * Modules\Core\Models\VerificationCode) and single-use (`used_at`). Regenerating (Modules\
     * Core\Services\MfaService::regenerateRecoveryCodes()) deletes every existing row for the
     * user and inserts a fresh config('core.mfa.recovery_code_count') set.
     */
    public function up(): void
    {
        if (Schema::hasTable('mfa_recovery_codes')) {
            return;
        }

        Schema::create('mfa_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_recovery_codes');
    }
};
