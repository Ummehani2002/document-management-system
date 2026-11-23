<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents') || Schema::hasColumn('documents', 'revision_no')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('revision_no', 16)->nullable()->after('reference_no');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents') || ! Schema::hasColumn('documents', 'revision_no')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('revision_no');
        });
    }
};
