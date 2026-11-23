<?php

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentMetaService;
use App\Support\LocalDateTime;

test('displayFor repairs without throwing', function () {
    $entity = Entity::create(['name' => 'Proscape']);
    $project = Project::create([
        'entity_id' => $entity->id,
        'project_number' => 'P1',
        'project_name' => 'Creek',
    ]);
    $ocr = "Inspection Details\nMIR No. PSE2024-1011-PRS-\nPAR-MIR-HLS-00042 Date 09-Oct-2024\nMaterials for Inspection Fila PW-10 Materials Approval Submittal Ref PSE20241011-PRS-HLS-MAR-00039\n";
    $doc = Document::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'discipline' => 'HARD LANDSCAPE',
        'document_type' => 'Material Inspection Request',
        'file_name' => 'PSE2024-1011-PRS-PAR-MIR-HLS-00042 R.00 (B)_Approved as noted.pdf',
        'file_path' => 'docs/a.pdf',
        'reference_no' => 'PAR-MIR-HLS-00042',
        'subject' => 'Fila PW-10 Materials Approval Submittal Ref HLS-MAR-00039',
        'ocr_text' => $ocr,
    ]);

    $shown = $doc->fresh();
    $meta = DocumentMetaService::displayFor($shown);

    expect($meta['reference_no'])->toBe('PSE2024-1011-PRS-PAR-MIR-HLS-00042');
    expect($meta['subject'])->toBe('Fila PW-10');
    expect(LocalDateTime::formatModel($shown, 'updated_at'))->not->toBe('');
});
