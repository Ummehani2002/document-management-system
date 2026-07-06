<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use App\Models\UserEntityAccess;
use App\Models\UserFolderAccess;
use App\Services\DocumentAccessService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_open_user_access_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('user-access.index'))
            ->assertOk()
            ->assertSee('User Access Control');
    }

    public function test_non_admin_cannot_open_user_access_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user-access.index'))
            ->assertForbidden();
    }

    public function test_folder_restriction_limits_document_types(): void
    {
        $entity = Entity::create(['name' => 'Acme']);
        $user = User::factory()->create();
        UserEntityAccess::create(['user_id' => $user->id, 'entity_id' => $entity->id]);
        UserFolderAccess::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'main_folder' => 'Financial Documents',
            'document_type' => 'Invoice',
        ]);

        $service = app(DocumentAccessService::class);

        $this->assertTrue($service->canAccessFolder($user, $entity->id, 'Financial Documents', 'Invoice'));
        $this->assertFalse($service->canAccessFolder($user, $entity->id, 'Financial Documents', 'Payment Voucher'));
    }

    public function test_entity_without_folder_rows_allows_all_folders(): void
    {
        $entity = Entity::create(['name' => 'Acme']);
        $user = User::factory()->create();
        UserEntityAccess::create(['user_id' => $user->id, 'entity_id' => $entity->id]);

        $service = app(DocumentAccessService::class);
        $tree = $service->accessibleFolderTreeForEntity($user, $entity->id);

        $this->assertNotEmpty($tree);
        $this->assertArrayHasKey('Financial Documents', $tree);
    }
}
