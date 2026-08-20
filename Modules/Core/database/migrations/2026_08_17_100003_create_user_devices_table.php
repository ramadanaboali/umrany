<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs "login from a new device" / "suspicious login attempt" detection
     * (Modules\Core\Services\DeviceRecognitionService). A real table, not inferred from Sanctum
     * tokens: tokens are deleted on logout/password-reset and carry no IP/user-agent, so token
     * history alone would false-positive every post-logout login as "new device". `fingerprint`
     * is sha256(user-agent + device name) — good enough to recognize a returning device without
     * storing anything more identifying than what the request already sends.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_devices')) {
            return;
        }

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
