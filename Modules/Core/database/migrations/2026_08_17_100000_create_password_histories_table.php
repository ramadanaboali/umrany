<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only — a row is written every time a password is set (register/change/reset, on
     * either the User or Admin side) and never updated or deleted. The morph lets one table serve
     * both `App\Models\User` and `Modules\Core\Models\Admin` without duplicating the reuse-check
     * logic per guard. See Modules\Core\Services\PasswordHistoryService and
     * docs/decisions/0013-config-driven-password-policy-and-history.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('password_histories')) {
            return;
        }

        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable'); // already indexes (type, id) together
            $table->string('password_hash');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
