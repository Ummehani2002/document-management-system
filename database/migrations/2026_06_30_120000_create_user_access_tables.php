<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Short index names: MySQL limits identifiers to 64 characters.
        if (! Schema::hasTable('user_entity_access')) {
            Schema::create('user_entity_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'entity_id'], 'uea_user_entity_unique');
            });
        }

        if (! Schema::hasTable('user_folder_access')) {
            Schema::create('user_folder_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->string('main_folder');
                $table->string('document_type')->nullable();
                $table->timestamps();

                $table->unique(
                    ['user_id', 'entity_id', 'main_folder', 'document_type'],
                    'ufa_user_folder_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_folder_access');
        Schema::dropIfExists('user_entity_access');
    }
};
