<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'azure_access_token')) {
                $table->text('azure_access_token')->nullable()->after('azure_id');
            }
            if (! Schema::hasColumn('users', 'azure_refresh_token')) {
                $table->text('azure_refresh_token')->nullable()->after('azure_access_token');
            }
            if (! Schema::hasColumn('users', 'azure_token_expires_at')) {
                $table->timestamp('azure_token_expires_at')->nullable()->after('azure_refresh_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['azure_access_token', 'azure_refresh_token', 'azure_token_expires_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
