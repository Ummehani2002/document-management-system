<?php

use App\Services\DocumentFilenameParser;

$matFilename = '1TB03300-007C33-PIC-MAT-IR-0002[C0] (B).pdf';

test('mat filename code is not reclassified as boq from incidental ocr text', function () use ($matFilename) {
    $result = DocumentFilenameParser::classifyForAutomation(
        $matFilename,
        "Project form\nBOQ Bill Of Quantities\nSome extracted schedule text"
    );

    expect($result['document_category'])->toBe('Material Submittal');
    expect($result['category_source'])->toBe('filename');
});

test('material submittal form title wins over boq mention in ocr body', function () use ($matFilename) {
    $ocr = "Material Submittal Form\nAMAALA\nGeneral Information\nProgram Name: TRIPLE BAY\nRef. No.: 1TB03300-007C33-PIC-MAT-IR-0002\nBill of Quantities schedule attached";

    expect(DocumentFilenameParser::guessSubfolderFromDocumentText($ocr))->toBe('Material Submittal');

    $result = DocumentFilenameParser::classifyForAutomation($matFilename, $ocr);

    expect($result['document_category'])->toBe('Material Submittal');
});

test('mat filename displays as material submittal even when stored type is stale boq', function () use ($matFilename) {
    expect(DocumentFilenameParser::folderSubLabel(
        'BOQ Bill Of Quantities',
        $matFilename,
        "Material Submittal Form\nBill of Quantities schedule attached"
    ))->toBe('Material Submittal');
});
