<?php

namespace Tests\Feature;

use App\Models\DocumentMainFolder;
use App\Models\DocumentSubfolder;
use App\Services\DocumentFolderCatalog;
use App\Services\DocumentFilenameParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFolderAutoClassifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DocumentFolderCatalog::clearCache();
    }

    public function test_auto_classify_matches_new_folder_from_filename(): void
    {
        $main = DocumentMainFolder::create([
            'name' => 'Contracts',
            'sort_order' => 1,
        ]);
        DocumentSubfolder::create([
            'main_folder_id' => $main->id,
            'name' => 'Service Agreement',
            'sort_order' => 1,
        ]);
        DocumentFolderCatalog::clearCache();

        $result = DocumentFilenameParser::classifyForAutomation(
            'PSE2026-Service Agreement-Final.pdf',
            null
        );

        $this->assertSame('Service Agreement', $result['document_category']);
        $this->assertSame('catalog_name', $result['category_source']);
        $this->assertGreaterThanOrEqual(0.70, $result['confidence']);
    }

    public function test_auto_classify_matches_new_folder_from_ocr_text(): void
    {
        $main = DocumentMainFolder::create([
            'name' => 'Contracts',
            'sort_order' => 1,
        ]);
        DocumentSubfolder::create([
            'main_folder_id' => $main->id,
            'name' => 'Service Agreement',
            'sort_order' => 1,
        ]);
        DocumentFolderCatalog::clearCache();

        $result = DocumentFilenameParser::classifyForAutomation(
            'scan-001.pdf',
            "Title: Service Agreement\nBetween Tanseeq and Contractor"
        );

        $this->assertSame('Service Agreement', $result['document_category']);
        $this->assertSame('catalog_name', $result['category_source']);
    }

    public function test_hard_coded_filename_rules_still_win_over_catalog_match(): void
    {
        $main = DocumentMainFolder::create([
            'name' => 'Contracts',
            'sort_order' => 1,
        ]);
        DocumentSubfolder::create([
            'main_folder_id' => $main->id,
            'name' => 'Service Agreement',
            'sort_order' => 1,
        ]);
        DocumentFolderCatalog::clearCache();

        $result = DocumentFilenameParser::classifyForAutomation(
            '1TB03300-007C33-PIC-MAT-IR-0002.pdf',
            'Service Agreement mentioned in appendix notes'
        );

        $this->assertSame('Material Submittal', $result['document_category']);
        $this->assertNotSame('catalog_name', $result['category_source']);
    }
}
