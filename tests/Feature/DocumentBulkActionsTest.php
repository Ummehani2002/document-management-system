<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\DocumentFolderCatalog;
use App\Services\EntityContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DocumentFolderCatalog::clearCache();
        Storage::fake(config('filesystems.default'));
    }

    public function test_search_shows_multi_select_download_move_and_copy(): void
    {
        [$admin, $entity, $project, $document] = $this->seedDocument('Prequalification', 'wrong-folder.pdf');

        $this->actingAs($admin)
            ->withSession([EntityContextService::SESSION_KEY => $entity->id])
            ->get(route('documents.search', [
                'entity_id' => $entity->id,
                'project_id' => $project->id,
            ]))
            ->assertOk()
            ->assertSee('class="bulk-select"', false)
            ->assertSee('>Download</button>', false)
            ->assertSee('>Move to</button>', false)
            ->assertSee('>Copy to</button>', false)
            ->assertSee('>Move</button>', false)
            ->assertSee('>Copy</button>', false)
            ->assertSee($document->file_name, false);
    }

    public function test_bulk_download_returns_zip_of_selected_files(): void
    {
        [$admin, $entity, $project, $first] = $this->seedDocument('Prequalification', 'one.pdf', 'first-file');
        $second = $this->storeDocument($entity, $project, 'Prequalification', 'two.pdf', 'second-file');

        $response = $this->actingAs($admin)
            ->post(route('documents.bulk.download'), [
                'ids' => [$first->id, $second->id],
            ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));
    }

    public function test_bulk_move_puts_file_in_selected_folder(): void
    {
        [$admin, $entity, $project, $document] = $this->seedDocument('Prequalification', 'misfiled.pdf');

        $this->actingAs($admin)
            ->from(route('documents.search', ['entity_id' => $entity->id, 'project_id' => $project->id]))
            ->post(route('documents.bulk.move'), [
                'ids' => [$document->id],
                'target_entity_id' => $entity->id,
                'target_project_id' => $project->id,
                'target_main_folder' => 'Transmittals Documents',
                'target_document_type' => 'Material Submittal',
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('Material Submittal', $document->document_type);
        $this->assertTrue((bool) $document->folder_locked);
        $this->assertStringContainsString('material-submittal', (string) $document->file_path);
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists($document->file_path));
        $this->assertDatabaseHas('user_activities', [
            'document_id' => $document->id,
            'action' => UserActivity::ACTION_MOVED,
        ]);
    }

    public function test_bulk_copy_creates_a_second_file_in_target_folder(): void
    {
        [$admin, $entity, $project, $document] = $this->seedDocument('Prequalification', 'source.pdf');

        $this->actingAs($admin)
            ->from(route('documents.search', ['entity_id' => $entity->id, 'project_id' => $project->id]))
            ->post(route('documents.bulk.copy'), [
                'ids' => [$document->id],
                'target_entity_id' => $entity->id,
                'target_project_id' => $project->id,
                'target_main_folder' => 'Transmittals Documents',
                'target_document_type' => 'Material Submittal',
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('Prequalification', $document->document_type);

        $copy = Document::query()->where('file_name', 'source.pdf')->where('id', '!=', $document->id)->first();
        $this->assertNotNull($copy);
        $this->assertSame('Material Submittal', $copy->document_type);
        $this->assertTrue((bool) $copy->folder_locked);
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists($copy->file_path));
        $this->assertDatabaseHas('user_activities', [
            'document_id' => $copy->id,
            'action' => UserActivity::ACTION_COPIED,
        ]);
    }

    public function test_bulk_move_is_forbidden_without_document_access(): void
    {
        [, $entity, $project, $document] = $this->seedDocument('Prequalification', 'locked.pdf');
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)
            ->post(route('documents.bulk.move'), [
                'ids' => [$document->id],
                'target_entity_id' => $entity->id,
                'target_project_id' => $project->id,
                'target_main_folder' => 'Transmittals Documents',
                'target_document_type' => 'Material Submittal',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Entity, 2: Project, 3: Document}
     */
    protected function seedDocument(string $documentType, string $fileName, string $contents = 'file-bytes'): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entity = Entity::create(['name' => 'Proscape']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => '1696',
            'project_name' => 'Murooj Al Furjan',
        ]);

        $document = $this->storeDocument($entity, $project, $documentType, $fileName, $contents);

        return [$admin, $entity, $project, $document];
    }

    protected function storeDocument(Entity $entity, Project $project, string $documentType, string $fileName, string $contents): Document
    {
        $path = 'documents/proscape/1696/'.\Illuminate\Support\Str::slug($documentType).'/'.$fileName;
        Storage::disk(config('filesystems.default'))->put($path, $contents);

        return Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => $documentType,
            'file_name' => $fileName,
            'file_path' => $path,
        ]);
    }
}
