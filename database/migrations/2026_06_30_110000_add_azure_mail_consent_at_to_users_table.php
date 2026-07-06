<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'azure_mail_consent_at')) {
                $table->timestamp('azure_mail_consent_at')->nullable()->after('azure_token_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'azure_mail_consent_at')) {
                $table->dropColumn('azure_mail_consent_at');
            }
        });
    }
};
