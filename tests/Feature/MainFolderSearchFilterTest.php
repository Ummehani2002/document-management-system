<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentFolderCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainFolderSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DocumentFolderCatalog::clearCache();
    }

    public function test_main_folder_search_returns_only_that_category_subfolders(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entity = Entity::create(['name' => 'Proscape']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => '1696',
            'project_name' => 'Murooj Al Furjan',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Invoice',
            'file_name' => 'invoice-1.pdf',
            'file_path' => 'documents/proscape/1696/invoice/invoice-1.pdf',
        ]);
        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Incoming Or Outgoing Letter',
            'file_name' => 'letter-1.pdf',
            'file_path' => 'documents/proscape/1696/letter/letter-1.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('documents.search', [
                'from_sidebar' => 1,
                'main_folder' => 'Financial Documents',
                'entity_id' => $entity->id,
                'project_id' => $project->id,
            ]))
            ->assertOk()
            ->assertSee('invoice-1.pdf')
            ->assertDontSee('letter-1.pdf')
            ->assertSee('Category: Financial Documents');
    }

    public function test_other_main_folders_also_filter_by_category_entity_project(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entity = Entity::create(['name' => 'Proscape']);
        $otherEntity = Entity::create(['name' => 'Other Co']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => '1696',
            'project_name' => 'Murooj Al Furjan',
        ]);
        $otherProject = Project::create([
            'entity_id' => $otherEntity->id,
            'project_number' => '9999',
            'project_name' => 'Other Project',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Incoming Or Outgoing Letter',
            'file_name' => 'letter-ok.pdf',
            'file_path' => 'documents/proscape/1696/letter/letter-ok.pdf',
        ]);
        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Invoice',
            'file_name' => 'invoice-hidden.pdf',
            'file_path' => 'documents/proscape/1696/invoice/invoice-hidden.pdf',
        ]);
        Document::create([
            'entity_id' => $otherEntity->id,
            'project_id' => $otherProject->id,
            'document_type' => 'Incoming Or Outgoing Letter',
            'file_name' => 'letter-other-entity.pdf',
            'file_path' => 'documents/other/9999/letter/letter-other-entity.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('documents.search', [
                'from_sidebar' => 1,
                'main_folder' => 'General Correspondence',
                'entity_id' => $entity->id,
                'project_id' => $project->id,
            ]))
            ->assertOk()
            ->assertSee('letter-ok.pdf')
            ->assertDontSee('invoice-hidden.pdf')
            ->assertDontSee('letter-other-entity.pdf')
            ->assertSee('Category: General Correspondence');
    }
}