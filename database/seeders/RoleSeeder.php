<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Create DMS roles: Admin, Project Manager, Document Controller, Viewer.
     */
    public function run(): void
    {
        $roles = ['Admin', 'Project Manager', 'Document Controller', 'Viewer'];

        foreach ($roles as $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        // Assign Admin to first user if exists (optional)
        $user = \App\Models\User::first();
        if ($user && !$user->hasRole('Admin')) {
            $user->assignRole('Admin');
        }
    }
}
