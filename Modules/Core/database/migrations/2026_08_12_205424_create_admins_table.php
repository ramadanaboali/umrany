<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins are a deliberately separate identity from `users` — distinct table, distinct
     * `admin` guard (config/auth.php), never reachable through a Sanctum API token. See
     * docs/architecture/admin-portal.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('admins')) {
            return;
        }

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable()->unique();
            $table->string('avatar_path')->nullable();
            // Bypasses all permission checks (Gate::before) regardless of role assignment —
            // a boolean, not a role name, so renaming/removing a "Super Admin" role can never
            // accidentally revoke the bypass. See docs/modules/core.md § Admin.
            $table->boolean('is_super_admin')->default(false);
            $table->string('status')->default('active'); // active|suspended
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
