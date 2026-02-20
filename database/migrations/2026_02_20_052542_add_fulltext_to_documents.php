<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fulltext index is MySQL-only; SQLite does not support it
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->fullText('ocr_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropFullText(['ocr_text']);
        });
    }
};
