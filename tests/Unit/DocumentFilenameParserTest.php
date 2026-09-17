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

test('wir filename stays work inspection even when ocr mentions taking over certificate', function () {
    $filename = 'CMO-PSP-DS21-WIR-000003_00_B.pdf';
    $ocr = "WORK INSPECTION REQUEST\nSubmittal Ref: CMO-PSP-DS21-WIR-000003\nArea: Tower\n"
        ."The Contractor hereby confirms items Comply to Contract Documents.\n"
        ."Sleeve size shall be approved shop drawings.\nApproved irrigation shop drawings shall be attached.\n"
        ."Checklist option: Taking Over Certificate TOC";

    $result = DocumentFilenameParser::classifyForAutomation($filename, $ocr);

    expect($result['document_category'])->toBe('Work Inspection');
});

test('minutes of progress meeting beats sd code in filename', function () {
    $filename = 'AWAJ-SD-802-190-25 -Minutes of Progress Meeting - 059.pdf';

    $result = DocumentFilenameParser::classifyForAutomation($filename, null);

    expect($result['document_category'])->toBe('MOM');
    expect($result['confidence'])->toBeGreaterThanOrEqual(0.70);
});

test('minutes ocr title beats sd code even without minutes words in filename stem alone', function () {
    $filename = 'AWAJ-SD-802-190-25-059.pdf';
    $ocr = "MINUTES OF PROGRESS MEETING # 059\nGolf Residence Fortimo\nPage 1 of 9";

    $result = DocumentFilenameParser::classifyForAutomation($filename, $ocr);

    expect($result['document_category'])->toBe('MOM');
});
