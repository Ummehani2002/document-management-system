<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTestLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_page_shows_username_password_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in with Microsoft')
            ->assertSee('or username / password')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);
    }

    public function test_user_can_login_with_username_and_password(): void
    {
        User::factory()->create([
            'username' => 'security',
            'email' => 'security@local.test',
            'password' => Hash::make('TestPass123!'),
        ]);

        $this->post('/login', [
            'username' => 'security',
            'password' => 'TestPass123!',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_security_test_user_command_creates_viewer(): void
    {
        $this->artisan('dms:security-test-user', [
            '--password' => 'SecurePass123!',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Username: security');

        $user = User::query()->where('username', 'security')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertTrue($user->hasRole('Viewer'));
        $this->assertFalse($user->hasRole('Admin'));
    }

    public function test_security_test_user_command_resets_password(): void
    {
        $user = User::factory()->create([
            'username' => 'security',
            'email' => 'security@local.test',
            'password' => Hash::make('OldPass123!'),
        ]);
        $user->assignRole('Viewer');

        $this->artisan('dms:security-test-user', [
            '--password' => 'NewPass123!',
        ])->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass123!', $user->password));
        $this->assertTrue($user->hasRole('Viewer'));
    }
}
