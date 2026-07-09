<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentFileVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentOlderVersionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin');
    }

    public function test_versions_endpoint_returns_older_revisions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'MH-0026',
            'project_name' => 'Test Project',
        ]);

        $older = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'MOM',
            'file_name' => 'RYM-PRO-POL-DT-0003 R.00 - Health Plan.pdf',
            'file_path' => 'documents/acme/mh-0026/mom/old.pdf',
        ]);

        $latest = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'MOM',
            'file_name' => 'RYM-PRO-POL-DT-0003 R.01 - Health Plan.pdf',
            'file_path' => 'documents/acme/mh-0026/mom/new.pdf',
        ]);

        $this->actingAs($user)
            ->getJson(route('documents.versions', ['id' => $latest->id]))
            ->assertOk()
            ->assertJsonPath('current.id', $latest->id)
            ->assertJsonCount(1, 'older_versions')
            ->assertJsonPath('older_versions.0.id', $older->id)
            ->assertJsonPath('older_versions.0.file_name', $older->file_name);
    }

    public function test_folder_search_shows_only_latest_version_per_family(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'MH-0026',
            'project_name' => 'Test Project',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'MOM',
            'file_name' => 'RYM-PRO-POL-DT-0003 R.00 - Health Plan.pdf',
            'file_path' => 'documents/acme/mh-0026/mom/old.pdf',
        ]);

        $latest = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'MOM',
            'file_name' => 'RYM-PRO-POL-DT-0003 R.01 - Health Plan.pdf',
            'file_path' => 'documents/acme/mh-0026/mom/new.pdf',
        ]);

        $response = $this->actingAs($user)->get(route('documents.search', [
            'from_sidebar' => 1,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'MOM',
            'main_folder' => 'Project Management',
        ]));

        $response->assertOk();
        $response->assertSee($latest->file_name, false);
        $response->assertDontSee('RYM-PRO-POL-DT-0003 R.00 - Health Plan.pdf', false);
    }

    public function test_keyword_search_shows_only_latest_version_per_family(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'MH-0026',
            'project_name' => 'Test Project',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'MOM',
            'file_name' => 'RYM-PRO-POL-DT-0003 R.00 - Health Plan.pdf',
            'file_path' => 'documents/acme/mh-0026/mom/old.pdf',
            'ocr_text' => 'Health Plan revision zero',
        ]);

        $latest = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'MOM',
            'file_name' => 'RYM-PRO-POL-DT-0003 R.01 - Health Plan.pdf',
            'file_path' => 'documents/acme/mh-0026/mom/new.pdf',
            'ocr_text' => 'Health Plan revision one',
        ]);

        $response = $this->actingAs($user)->get(route('documents.search', [
            'keyword' => 'Health Plan',
        ]));

        $response->assertOk();
        $response->assertSee($latest->file_name, false);
        $response->assertDontSee('RYM-PRO-POL-DT-0003 R.00 - Health Plan.pdf', false);
        $response->assertSee('Older versions (1)', false);
    }

    public function test_pick_latest_document_ids_prefers_higher_revision(): void
    {
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'MH-0026',
            'project_name' => 'Test Project',
        ]);

        $older = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'Report R.00.pdf',
            'file_path' => 'documents/acme/mh-0026/other/report-r00.pdf',
        ]);

        $latest = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'Report R.01.pdf',
            'file_path' => 'documents/acme/mh-0026/other/report-r01.pdf',
        ]);

        $latestIds = DocumentFileVersioning::pickLatestDocumentIds(
            Document::query()->where('project_id', $project->id)->get(['id', 'file_name', 'project_id'])
        );

        $this->assertSame([$latest->id], $latestIds);
        $this->assertNotContains($older->id, $latestIds);
    }
}
