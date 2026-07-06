<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class GrantUserAdminCommand extends Command
{
    protected $signature = 'dms:grant-admin {email : User email address}';

    protected $description = 'Grant Admin role (full access to all entities, folders, and documents).';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if ($email === '') {
            $this->error('Email is required.');

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user === null) {
            $this->error("No user found with email: {$email}");
            $this->line('The user must sign in at least once (Microsoft or local login) before granting access.');

            return self::FAILURE;
        }

        Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['name' => 'Admin', 'guard_name' => 'web']
        );

        $user->syncRoles(['Admin']);
        $user->entityAccess()->delete();
        $user->folderAccess()->delete();

        $this->info("Admin granted to {$user->email} ({$user->name}). Full access to all entities and folders.");

        return self::SUCCESS;
    }
}
