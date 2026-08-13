<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kept separate from the end-user `password_reset_tokens` table (email-keyed) so an email
     * address that happens to exist on both an Admin and a User row can never cross-reset the
     * wrong account. Shape matches Laravel's own password-broker table so the stock
     * DatabaseTokenRepository can be reused for the `admins` broker (config/auth.php).
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_password_reset_tokens')) {
            return;
        }

        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
    }
};
