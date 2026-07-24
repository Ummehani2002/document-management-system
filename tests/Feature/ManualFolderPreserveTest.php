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

    public function test_short_catalog_name_requires_word_boundary(): void
    {
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

        $fromFilename = DocumentFilenameParser::classifyForAutomation('test8.pdf', null);
        $this->assertSame('Other', $fromFilename['document_category']);

        $fromTitle = DocumentFilenameParser::classifyForAutomation(
            'scan.pdf',
            "Document Title: Test\nProject notes"
        );
        $this->assertSame('Test', $fromTitle['document_category']);
        $this->assertSame('catalog_name', $fromTitle['category_source']);
    }
}
