<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PROVIDER-007: certificates, awards, memberships, official recognitions.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_certificates')) {
            return;
        }

        Schema::create('provider_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('issuer')->nullable();
            $table->string('file_path')->nullable();
            $table->date('issued_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_certificates');
    }
};
