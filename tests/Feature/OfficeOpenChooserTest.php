<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentPreviewUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfficeOpenChooserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin');
    }

    public function test_excel_view_shows_dms_chooser_instead_of_raw_redirect(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $entity = Entity::create(['name' => 'Metaline LLC']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'MLI20261082',
            'project_name' => 'Test',
        ]);
        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'test copy.xlsx',
            'file_path' => 'documents/metaline-llc/mli20261082/other/test-copy.xlsx',
        ]);

        $this->actingAs($user)
            ->get(route('documents.view', ['id' => $document->id]))
            ->assertOk()
            ->assertSee('Edit in DMS', false)
            ->assertSee('Do not use “Edit a copy”', false)
            ->assertSee('test copy.xlsx', false);
    }

    public function test_office_files_are_detected(): void
    {
        $this->assertTrue(DocumentPreviewUrl::isOfficeFile('a.xlsx'));
        $this->assertTrue(DocumentPreviewUrl::isOfficeFile('a.XLS'));
        $this->assertTrue(DocumentPreviewUrl::isOfficeFile('a.docx'));
        $this->assertFalse(DocumentPreviewUrl::isOfficeFile('a.pdf'));
    }

    public function test_presigned_redirect_is_disabled_for_excel(): void
    {
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'P1',
            'project_name' => 'Test',
        ]);
        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'sheet.xlsx',
            'file_path' => 'documents/acme/p1/other/sheet.xlsx',
        ]);

        $this->assertNull(DocumentPreviewUrl::presignedRedirectUrl($document));
    }
}
