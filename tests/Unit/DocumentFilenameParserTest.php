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

test('pan report filename goes to project award notification not monthly report', function () {
    $result = DocumentFilenameParser::classifyForAutomation('PSE20251025_PANReport.pdf', null);

    expect($result['document_category'])->toBe('Project Award Notification');
    expect($result['confidence'])->toBeGreaterThanOrEqual(0.70);
});

test('sdr filename goes to shop drawing not engineers instruction', function () {
    $filename = '4692-ABC-WIM-EYWA-SDR-ARC-001-00-Main Pool Setting Out Layout Level 1_BSB_B.pdf';
    $ocr = "Drawing legend\nEI = Engineers Instruction\nSD = Shop Drawings\nMain Pool Setting Out";

    $result = DocumentFilenameParser::classifyForAutomation($filename, $ocr);

    expect($result['document_category'])->toBe('Shop Drawing');
});

test('internal memo ocr wins over incidental boq mention', function () {
    $ocr = "INTERNAL MEMO\nTo: Project Team\nSubject: Budget note\nPlease review BOQ Bill Of Quantities attachment.";

    expect(DocumentFilenameParser::guessSubfolderFromDocumentText($ocr))->toBe('Internal Memo');

    $result = DocumentFilenameParser::classifyForAutomation('PSE20261002.pdf', $ocr);

    expect($result['document_category'])->toBe('Internal Memo');
});
