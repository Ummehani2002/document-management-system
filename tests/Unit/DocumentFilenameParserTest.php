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

test('prequalification for laboratory testing is not testing and commissioning', function () {
    $filename = 'DXB2-PSP-LS-SCAR-000001-02_A.pdf';
    $ocr = "Subject: Pre-qualification Document for Laboratory Testing (Independent Laboratory L.L.C)\n"
        ."Document Type: Pre-Qualification\nDescription: Pre-qualification Document for Laboratory Testing\n"
        ."For GHD/RED Engineering COMMENTS\nNo Objection.";

    expect(DocumentFilenameParser::guessSubfolderFromDocumentText($ocr))->toBe('Prequalification');

    $result = DocumentFilenameParser::classifyForAutomation($filename, $ocr);

    expect($result['document_category'])->toBe('Prequalification');
});

test('preq filename code goes to prequalification', function () {
    $result = DocumentFilenameParser::classifyForAutomation('PSE20231011-PRS-PREQ-00001 R.00.pdf', null);

    expect($result['document_category'])->toBe('Prequalification');
});

test('sample register filenames map to the sharepoint folders', function (string $filename, string $folder) {
    $result = DocumentFilenameParser::classifyForAutomation($filename, null);

    expect($result['document_category'])->toBe($folder);
})->with([
    ['AB-0003-R000-Staircase-ST-PO-01-Plan.pdf', 'As Built Drawing Submittal'],
    ['RYM-PRO-POL-DT-0003 R.00 - Health.pdf', 'Document Transmittal'],
    ['DXB2-LOR-PSP-LTR-00001_Programme.pdf', 'Incoming Or Outgoing Letter'],
    ['PSE2024-10111-PRS-PAR-MIR-HLS-00001.pdf', 'Material Inspection Request'],
    ['PSE20241019-WG-PRO-MAS-0001 - Code.pdf', 'Material Sample'],
    ['P158.01_MAT_INFRA2_CIV-0003-R1.pdf', 'Material Submittal'],
    ['1TB02012-012C20-PIC-MTS-0001[C4].pdf', 'Method Statement'],
    ['C1-C2-CSCEE-XLN-MEST-0055_R00.pdf', 'Method Statement'],
    ['UNEC-PJA-SDS-INF-FRN-001-R1-Sewer.pdf', 'Shop Drawing'],
    ['Al Jada BOQ.pdf', 'BOQ Bill Of Quantities'],
    ['PJE20231001-BK-CVI-0050 Bench.pdf', 'Confirmation Of Verbal Instruction'],
    ['QOR-PRO-BK-0001_Closed.pdf', 'Quality Observation Report'],
    ['NCR 0003.pdf', 'NCR'],
    ['NCR-CIVIL-002 Rev02 AAN.pdf', 'NCR'],
    ['PJE20231001-BK-O&M-0004 Rev01.pdf', 'Operation And Maintenance Manual'],
    ['Coordination MOM No. 008.pdf', 'MOM'],
    ['202406192329 Minutes Of Meeting - PJE2.pdf', 'MOM'],
    ['211106-LWWN-UA-EI-002.pdf', 'Engineers Instruction'],
    ['0009.Engineer\'s Instruction No.002.pdf', 'Engineers Instruction'],
    ['P158.01_RFI_Morocco_CIV-0002-R0.pdf', 'Request For Information'],
    ['F1004_SON_010 - Closed.pdf', 'Site Observation Report'],
    ['PJE20231001-AV-L0039-23 - Cost Variation.pdf', 'Variation'],
    ['MASAS-P2-LSC-WAR-HLS-PSC-PA.pdf', 'Warranty By Us'],
    ['LTR-0174 Section Taking Over Certificate.pdf', 'Taking Over Certificate'],
    ['PSE20231015-F1004-DS-0009 Rev00 AAN.pdf', 'Design Calculation'],
]);

test('shop drawing sketch submittal form goes to shop drawing', function () {
    $filename = 'PIE20241002-7622-PSC-N-SR-0012-REV-01_AAN.pdf';
    $ocr = "SHOP DRAWING / SKETCH SUBMITTAL\nSubmittal No: PIE20241002-7622-PSC-FFN-SR-0012";

    $result = DocumentFilenameParser::classifyForAutomation($filename, $ocr);

    expect($result['document_category'])->toBe('Shop Drawing');
});
