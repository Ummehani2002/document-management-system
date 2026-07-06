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

    public function test_document_grant_allows_access_without_entity(): void
    {
        $entity = Entity::create(['name' => 'Acme']);
        $otherEntity = Entity::create(['name' => 'Other Co']);
        $user = User::factory()->create();

        $project = \App\Models\Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Test',
        ]);
        $allowed = \App\Models\Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'file_name' => 'allowed.pdf',
            'file_path' => 'documents/test/allowed.pdf',
            'document_type' => 'Other',
        ]);
        $denied = \App\Models\Document::create([
            'entity_id' => $otherEntity->id,
            'project_id' => $project->id,
            'file_name' => 'denied.pdf',
            'file_path' => 'documents/test/denied.pdf',
            'document_type' => 'Other',
        ]);

        $service = app(DocumentAccessService::class);
        $service->syncUserDocumentAccess($user, [$allowed->id]);

        $this->assertTrue($service->canAccessDocument($user, $allowed));
        $this->assertFalse($service->canAccessDocument($user, $denied));
    }

    public function test_admin_can_create_user_via_access_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entity = Entity::create(['name' => 'Acme']);

        $this->actingAs($admin)
            ->post(route('user-access.store'), [
                'name' => 'New User',
                'email' => 'newuser@tanseeqinvestment.com',
                'role' => 'Viewer',
                'entity_ids' => [$entity->id],
            ])
            ->assertRedirect(route('user-access.index'));

        $this->assertDatabaseHas('users', ['email' => 'newuser@tanseeqinvestment.com']);
    }
}
