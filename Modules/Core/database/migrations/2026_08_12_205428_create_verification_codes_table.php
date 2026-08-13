<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-use, time-limited codes for both account-verification channels (email + mobile) —
     * one mechanism, not a link-based flow for email and a code-based flow for mobile, since
     * mobile can't do signed URLs and the source spec models this as one VerificationCode
     * entity (docs/modules/core.md § Auth). Codes are stored hashed, never in plaintext.
     */
    public function up(): void
    {
        if (Schema::hasTable('verification_codes')) {
            return;
        }

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // email|mobile — delivery channel
            // Purpose is deliberately a separate column from `type` — `type` is the delivery
            // channel, `purpose` is what the code authorizes. This is what lets a mobile-only
            // user (no email on the account) reset their password via the same OTP mechanism
            // used for account verification, instead of Laravel's email-only password broker.
            $table->string('purpose')->default('account_verification'); // account_verification|password_reset
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
