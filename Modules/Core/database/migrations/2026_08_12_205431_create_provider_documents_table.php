<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('provider_documents')) {
            return;
        }

        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // commercial_registration|business_license|national_id|other
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_documents');
    }
};
