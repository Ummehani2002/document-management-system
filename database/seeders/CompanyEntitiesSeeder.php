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
        $names = [
            'Metaline LLC',
            'Tanseeq LLC',
            'Stones and Slates LLC',
        ];

        foreach ($names as $name) {
            Entity::query()->firstOrCreate(['name' => $name]);
        }
    }
}
