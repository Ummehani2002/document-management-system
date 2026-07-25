<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_subfolders', function (Blueprint $table) {
            $table->text('auto_keywords')->nullable()->after('name');
        });

        // Default: folder name is the first auto-detect keyword.
        $rows = DB::table('document_subfolders')->whereNull('auto_keywords')->get(['id', 'name']);
        foreach ($rows as $row) {
            DB::table('document_subfolders')
                ->where('id', $row->id)
                ->update(['auto_keywords' => $row->name]);
        }
    }

    public function down(): void
    {
        Schema::table('document_subfolders', function (Blueprint $table) {
            $table->dropColumn('auto_keywords');
        });
    }
};
