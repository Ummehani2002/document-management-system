<?php

namespace Tests\Feature;

use App\Models\DocumentMainFolder;
use App\Models\DocumentSubfolder;
use App\Models\User;
use App\Services\DocumentFolderCatalog;
use App\Services\DocumentFilenameParser;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderAutoKeywordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DocumentFolderCatalog::clearCache();
    }

    public function test_auto_upload_matches_custom_keywords(): void
    {
        $main = DocumentMainFolder::create([
            'name' => 'Contracts',
            'sort_order' => 1,
        ]);
        DocumentSubfolder::create([
            'main_folder_id' => $main->id,
            'name' => 'Service Agreement',
            'auto_keywords' => 'Service Agreement, SA-AGR, SVCAGR',
            'sort_order' => 1,
        ]);
        DocumentFolderCatalog::clearCache();

        $byCode = DocumentFilenameParser::classifyForAutomation(
            'PSE2026-SA-AGR-0001.pdf',
            null
        );
        $this->assertSame('Service Agreement', $byCode['document_category']);
        $this->assertSame('catalog_name', $byCode['category_source']);

        $byOcr = DocumentFilenameParser::classifyForAutomation(
            'scan.pdf',
            "Title: SVCAGR\nParties agree as follows"
        );
        $this->assertSame('Service Agreement', $byOcr['document_category']);
    }

    public function test_admin_can_save_auto_keywords_on_subfolder(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $main = DocumentMainFolder::create([
            'name' => 'Contracts',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('folders.subfolders.store', $main), [
                'name' => 'Test',
                'auto_keywords' => 'Test, TST, TESTDOC',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('folders.index'));

        $sub = DocumentSubfolder::query()->where('name', 'Test')->first();
        $this->assertNotNull($sub);
        $this->assertStringContainsString('TESTDOC', (string) $sub->auto_keywords);

        DocumentFolderCatalog::clearCache();
        $result = DocumentFilenameParser::classifyForAutomation('PSE-TESTDOC-01.pdf', null);
        $this->assertSame('Test', $result['document_category']);
    }
}
