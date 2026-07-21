<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateSecurityTestUserCommand extends Command
{
    protected $signature = 'dms:security-test-user
                            {--password= : Password for the security test account}
                            {--username=security : Login username}
                            {--email=security@local.test : Account email}';

    protected $description = 'Create or reset the security-team username/password test account (Viewer role).';

    public function handle(): int
    {
        $username = strtolower(trim((string) $this->option('username')));
        $email = strtolower(trim((string) $this->option('email')));
        $password = (string) ($this->option('password') ?? '');

        if ($username === '' || $email === '') {
            $this->error('Username and email are required.');

            return self::FAILURE;
        }

        if ($password === '') {
            $password = (string) $this->secret('Password for security test user');
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        Role::firstOrCreate(
            ['name' => 'Viewer', 'guard_name' => 'web'],
            ['name' => 'Viewer', 'guard_name' => 'web']
        );

        $user = User::query()
            ->where('username', $username)
            ->orWhereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user === null) {
            $user = User::create([
                'name' => 'Security Tester',
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $this->info('Created security test user.');
        } else {
            $user->name = 'Security Tester';
            $user->username = $username;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->save();
            $this->info('Updated existing security test user.');
        }

        $user->syncRoles(['Viewer']);

        $this->newLine();
        $this->line('Share these credentials with the security team:');
        $this->line('  Username: '.$username);
        $this->line('  Password: '.$password);
        $this->newLine();
        $this->comment('Role: Viewer. Grant Entity / Project / Folders in User Access before they can see documents.');
        $this->comment('For full-system testing, change their role to Admin in User Access.');

        return self::SUCCESS;
    }
}
