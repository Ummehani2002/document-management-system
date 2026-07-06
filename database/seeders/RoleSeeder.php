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

        $adminEmail = strtolower(trim((string) env('DMS_ADMIN_EMAIL', '')));
        $user = $adminEmail !== ''
            ? \App\Models\User::query()->whereRaw('LOWER(email) = ?', [$adminEmail])->first()
            : \App\Models\User::first();

        if ($user && ! $user->hasRole('Admin')) {
            $user->syncRoles(['Admin']);
            $user->entityAccess()->delete();
            $user->folderAccess()->delete();
        }
    }
}
