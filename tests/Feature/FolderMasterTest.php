<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentMainFolder;
use App\Models\DocumentSubfolder;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserFolderAccess;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentFolderCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DocumentFolderCatalog::clearCache();
    }

    public function test_admin_can_open_folder_master(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get(route('folders.index'))
            ->assertOk()
            ->assertSee('Folder Master')
            ->assertSee('Financial Documents');
    }

    public function test_non_admin_cannot_open_folder_master(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('folders.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_subfolder_and_tree_reflects_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $main = DocumentMainFolder::query()->where('name', 'Transmittals Documents')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('folders.subfolders.store', $main), [
                'name' => 'Custom Inspection Report',
                'sort_order' => 99,
            ])
            ->assertRedirect(route('folders.index'));

        DocumentFolderCatalog::clearCache();
        $tree = DocumentFilenameParser::sidebarFolderTree();

        $this->assertContains('Custom Inspection Report', $tree['Transmittals Documents']);
    }

    public function test_renaming_subfolder_updates_document_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entity = Entity::create(['name' => 'Alpha']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'A-001',
            'project_name' => 'Alpha Project',
        ]);

        $sub = DocumentSubfolder::query()->where('name', 'Invoice')->firstOrFail();

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Invoice',
            'file_name' => 'inv.pdf',
            'file_path' => 'documents/inv.pdf',
        ]);

        UserFolderAccess::create([
            'user_id' => $admin->id,
            'entity_id' => $entity->id,
            'main_folder' => 'Financial Documents',
            'document_type' => 'Invoice',
        ]);

        $this->actingAs($admin)
            ->put(route('folders.subfolders.update', $sub), [
                'main_folder_id' => $sub->main_folder_id,
                'name' => 'Customer Invoice',
                'sort_order' => $sub->sort_order,
            ])
            ->assertRedirect(route('folders.index'));

        $this->assertDatabaseHas('documents', ['document_type' => 'Customer Invoice']);
        $this->assertDatabaseMissing('documents', ['document_type' => 'Invoice']);
        $this->assertDatabaseHas('user_folder_access', ['document_type' => 'Customer Invoice']);
        $this->assertContains('Customer Invoice', DocumentFilenameParser::sidebarFolderTree()['Financial Documents']);
    }
}
