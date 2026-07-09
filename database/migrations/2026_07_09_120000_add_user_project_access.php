<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_project_access')) {
            Schema::create('user_project_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'project_id'], 'upa_user_project_unique');
            });
        }

        if (Schema::hasTable('user_folder_access') && ! Schema::hasColumn('user_folder_access', 'project_id')) {
            Schema::table('user_folder_access', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('entity_id')->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_folder_access') && Schema::hasColumn('user_folder_access', 'project_id')) {
            Schema::table('user_folder_access', function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_id');
            });
        }

        Schema::dropIfExists('user_project_access');
    }
};
