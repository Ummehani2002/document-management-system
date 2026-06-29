<?php

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentFilenameParser;

test('material submittal folder search includes mat files stored as boq', function () {
    $entity = Entity::create(['name' => 'Test Entity']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'PKE20231003',
        'project_name' => 'Test Project',
    ]);

    Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'document_type' => 'BOQ Bill Of Quantities',
        'file_name' => '1TB03300-007C33-PIC-MAT-IR-0001[C0] (A).pdf',
        'file_path' => 'documents/test-entity/pke20231003/boq-bill-of-quantities/file.pdf',
    ]);

    $count = DocumentFilenameParser::applyFolderTypeFilter(
        Document::query()->where('project_id', $project->id),
        ['Material Submittal']
    )->count();

    expect($count)->toBe(1);
});

test('boq folder search excludes mat coded files', function () {
    $entity = Entity::create(['name' => 'Test Entity']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'PKE20231003',
        'project_name' => 'Test Project',
    ]);

    Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'document_type' => 'BOQ Bill Of Quantities',
        'file_name' => '1TB03300-007C33-PIC-MAT-IR-0001[C0] (A).pdf',
        'file_path' => 'documents/test-entity/pke20231003/boq-bill-of-quantities/file.pdf',
    ]);

    Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'document_type' => 'BOQ Bill Of Quantities',
        'file_name' => 'BOQ-Cluster-1.pdf',
        'file_path' => 'documents/test-entity/pke20231003/boq-bill-of-quantities/boq.pdf',
    ]);

    $count = DocumentFilenameParser::applyFolderTypeFilter(
        Document::query()->where('project_id', $project->id),
        ['BOQ Bill Of Quantities']
    )->count();

    expect($count)->toBe(1);
});
