<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_document_access')) {
            return;
        }

        Schema::create('user_document_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'document_id'], 'uda_user_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_document_access');
    }
};
