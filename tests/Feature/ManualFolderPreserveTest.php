<?php

namespace Tests\Feature;

use App\Jobs\ProcessOCR;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentFolderCatalog;
use App\Services\DocumentFilenameParser;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualFolderPreserveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DocumentFolderCatalog::clearCache();
        Storage::fake(config('filesystems.default'));
    }

    public function test_manual_upload_keeps_custom_folder_after_ocr_job(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entity = Entity::create(['name' => 'Proscape']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => '1696',
            'project_name' => 'Murooj Al Furjan',
        ]);

        $main = \App\Models\DocumentMainFolder::create([
            'name' => 'Custom Main',
            'sort_order' => 99,
        ]);
        \App\Models\DocumentSubfolder::create([
            'main_folder_id' => $main->id,
            'name' => 'Test',
            'sort_order' => 1,
        ]);
        DocumentFolderCatalog::clearCache();

        $file = UploadedFile::fake()->create('test8.pdf', 20, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('documents.store'), [
                'upload_mode' => 'manual',
                'entity_id' => $entity->id,
                'project_id' => $project->id,
                'main_folder' => 'Custom Main',
                'document_type' => 'Test',
                'documents' => [$file],
            ])
            ->assertRedirect();

        $document = Document::query()->where('file_name', 'like', 'test8%')->first();
        $this->assertNotNull($document);
        $this->assertSame('Test', $document->document_type);

        // Simulate OCR suggesting a different known category — folder must stay locked.
        $document->update(['ocr_text' => "INVOICE\nTax Invoice\nAmount Due"]);
        (new ProcessOCR($document->id, true))->handle();

        $document->refresh();
        $this->assertSame('Test', $document->document_type);
    }

    public function test_sidebar_search_finds_documents_in_custom_test_folder(): void
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
            'document_type' => 'Test',
            'file_name' => 'test8.pdf',
            'file_path' => 'documents/proscape/1696/test/test8.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('documents.search', [
                'from_sidebar' => 1,
                'entity_id' => $entity->id,
                'project_id' => $project->id,
                'main_folder' => 'Custom Main',
                'document_type' => 'Test',
            ]))
            ->assertOk()
            ->assertSee('test8.pdf')
            ->assertDontSee('No files found in this folder for selected project.');
    }

    public function test_manual_material_submittal_not_moved_to_prequalification_after_ocr(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entity = Entity::create(['name' => 'Proscape']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => '1696',
            'project_name' => 'Murooj Al Furjan',
        ]);

        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Material Submittal',
            'file_name' => 'MAT-001-material.pdf',
            'file_path' => 'documents/proscape/1696/material-submittal/mat-001.pdf',
            'ocr_text' => "PREQUALIFICATION FORM\nVendor pre-qualification questionnaire\nMaterial Submittal Register",
        ]);

        (new ProcessOCR($document->id, true))->handle();

        $document->refresh();
        $this->assertSame('Material Submittal', $document->document_type);
    }

    public function test_direct_upload_finalize_keeps_manual_folder_and_locks_ocr(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $entity = Entity::create(['name' => 'Proscape']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => '1696',
            'project_name' => 'Murooj Al Furjan',
        ]);

        $disk = (string) config('filesystems.default');
        $key = 'documents/proscape/1696/material-submittal/'.uniqid('mat_', true).'.pdf';
        Storage::disk($disk)->put($key, 'fake-pdf-bytes');

        $this->actingAs($admin);

        $controller = app(\App\Http\Controllers\DocumentDirectUploadController::class);
        $method = new \ReflectionMethod($controller, 'finalizeUploadedObject');
        $method->setAccessible(true);

        $response = $method->invoke($controller, [
            'disk' => $disk,
            'original_name' => 'MAT-SS-001.pdf',
            'stored_file_name' => 'MAT-SS-001.pdf',
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'category' => 'Material Submittal',
            'upload_mode' => 'manual',
            'file_size' => 14,
            'orphan_document_id' => null,
        ], $key);

        $this->assertSame(200, $response->getStatusCode());

        $document = Document::query()->where('file_name', 'MAT-SS-001.pdf')->first();
        $this->assertNotNull($document);
        $this->assertSame('Material Submittal', $document->document_type);

        $document->update([
            'ocr_text' => "PREQUALIFICATION\nPRE-QUALIFICATION QUESTIONNAIRE\nPQ-123",
        ]);
        (new ProcessOCR($document->id, true))->handle();
        $document->refresh();
        $this->assertSame('Material Submittal', $document->document_type);
    }
}
