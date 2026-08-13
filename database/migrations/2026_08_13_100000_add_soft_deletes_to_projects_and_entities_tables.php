<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && ! Schema::hasColumn('projects', 'deleted_at')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('entities') && ! Schema::hasColumn('entities', 'deleted_at')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'deleted_at')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('entities') && Schema::hasColumn('entities', 'deleted_at')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
