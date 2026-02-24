<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAzureIdToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'azure_id')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('azure_id')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'azure_id')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('azure_id');
        });
    }
}
