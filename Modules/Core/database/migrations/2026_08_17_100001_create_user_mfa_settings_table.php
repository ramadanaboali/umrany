<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per user, 1:1 — kept separate from `users` rather than columns on it, matching the
     * `UserProfile`-is-separate-from-`User` precedent already in this codebase. `secret` is
     * `encrypted` at the model-cast level (TOTP genuinely needs the plaintext back to verify a
     * code, unlike a password hash) — rotating APP_KEY orphans every enrolled user, see
     * Modules/Core/CLAUDE.md § Gotchas. `confirmed_at` stays null until the enrollment is proven
     * with one real code — an unconfirmed secret never gates login (see
     * Modules\Core\Services\MfaService and docs/decisions/0012-totp-mfa-with-two-step-login.md).
     * `last_used_timestamp` is the google2fa time-slice counter of the last accepted code —
     * verification uses verifyKeyNewer() against it so the same 6 digits can't replay within the
     * same 30s window.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_mfa_settings')) {
            return;
        }

        Schema::create('user_mfa_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('secret');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('last_used_timestamp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_settings');
    }
};
