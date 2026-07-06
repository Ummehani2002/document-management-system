<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentFileVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentEditVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_edit_save_becomes_v1(): void
    {
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'MH-0026',
            'project_name' => 'Test',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'Undertaking letter.pdf',
            'file_path' => 'documents/acme/mh-0026/other/undertaking.pdf',
        ]);

        $next = DocumentFileVersioning::buildNextEditVersionFilename(
            'Undertaking letter.pdf',
            $project->id,
            'Other'
        );

        $this->assertSame('Undertaking letter V1.pdf', $next);
    }

    public function test_second_edit_save_becomes_v2(): void
    {
        $entity = Entity::create(['name' => 'Acme']);
        $project = Project::create([
            'entity_id' => $entity->id,
            'project_number' => 'MH-0026',
            'project_name' => 'Test',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'Undertaking letter.pdf',
            'file_path' => 'documents/acme/mh-0026/other/undertaking.pdf',
        ]);

        Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'document_type' => 'Other',
            'file_name' => 'Undertaking letter V1.pdf',
            'file_path' => 'documents/acme/mh-0026/other/undertaking-v1.pdf',
        ]);

        $next = DocumentFileVersioning::buildNextEditVersionFilename(
            'Undertaking letter V1.pdf',
            $project->id,
            'Other'
        );

        $this->assertSame('Undertaking letter V2.pdf', $next);
    }
}
