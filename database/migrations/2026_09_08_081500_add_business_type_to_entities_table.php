<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('business_type', 32)->default('construction')->after('name');
        });

        $entities = DB::table('entities')->select('id', 'name')->get();

        foreach ($entities as $entity) {
            $key = mb_strtolower(trim((string) $entity->name));
            $key = preg_replace('/\s+/', ' ', $key) ?? $key;

            $type = 'construction';
            if (str_contains($key, 'metaline')
                || (str_contains($key, 'stones') && str_contains($key, 'slates'))
                || str_contains($key, 'tanseeq llc')
                || $key === 'tanseeq'
            ) {
                $type = 'trading';
            }

            DB::table('entities')->where('id', $entity->id)->update(['business_type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
