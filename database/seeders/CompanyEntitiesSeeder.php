<?php

namespace Database\Seeders;

use App\Models\Entity;
use Illuminate\Database\Seeder;

class CompanyEntitiesSeeder extends Seeder
{
    /**
     * Ensure company entities exist (safe to re-run).
     */
    public function run(): void
    {
        $companies = [
            ['name' => 'Metaline LLC', 'business_type' => Entity::TYPE_TRADING],
            ['name' => 'Tanseeq LLC', 'business_type' => Entity::TYPE_TRADING],
            ['name' => 'Stones and Slates LLC', 'business_type' => Entity::TYPE_TRADING],
        ];

        foreach ($companies as $company) {
            Entity::query()->updateOrCreate(
                ['name' => $company['name']],
                ['business_type' => $company['business_type']]
            );
        }
    }
}
