<?php

namespace App\Services;

use App\Models\Project;

class DocumentFilenameParser
{
    /** Subfolders used in left navigation/upload placement */
    protected static array $subfolders = [
        'Bank Gurantees',
        'Invoice',
        'Payment Voucher',
        'Proforma Invoice',
        'Receipt Voucher',
        'Sales Credit Note',
        'Supplier Delivery Note',
        'Supplier Invoice',
        'Supplier Time Sheets',
        'Incoming Or Outgoing Letter',
        'Internal Memo',
        'KPI Report',
        'Monthly Report',
        'Payment Certificate',
        'Project Award Notification',
        'Snags',
        'Spare Parts',
        'Defect Liability Certificate',
        'Engineers Correspondences',
        'Engineers Instruction',
        'MOM',
        'NCR',
        'Operation And Maintenance Manual',
        'Payment Application',
        'Quality Observation Report',
        'Request For Information',
        'Site Observation Report',
        'Site Incident Report',
        'Taking Over Certificate',
        'Testing And Commissioning',
        'Variation',
        'Warranty By Us',
        'Change Request',
        'Design Calculation',
        'Confirmation Of Verbal Instruction',
        'Project Commercial Documents',
        'Catalogs',
        'Delivery Order',
        'Enquireis',
        'Good Receipt Note',
        'Material Issue Note',
        'Material Return Note',
        'Purchase Order',
        'Purchase Request',
        'Quotations',
        'Sales Order',
        'Trade License certificate',
        'VAT Registration Certificate',
        'Vendor Registration certificate',
        'As Built Drawing Submittal',
        'Material Submittal',
        'Material Inspection Request',
        'Method Statement',
        'Prequalification',
        'Shop Drawing',
        'Work Inspection',
        'Document Transmittal',
        'Material Sample',
        'Other',
    ];

    /**
     * Parse a PDF filename and suggest project number and subfolder.
     * E.g. "PSE20231011-PRS-PAR-DTF-00056 R.00 - Monthly Progress Report No.5 as of 20 Nov 2023.pdf"
     * → project_number: PSE20231011, category: Report
     */
    public static function parse(string $filename): array
    {
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        $upper = strtoupper($filename);

        $projectNumber = self::extractProjectNumber($filename);
        $subfolder = self::guessSubfolderFromTitle($filename, $upper);

        return [
            'project_number' => $projectNumber,
            'document_category' => $subfolder,
        ];
    }

    /**
     * Extract project number: first segment before hyphen (e.g. PSE20231011 from PSE20231011-PRS-PAR-DTF-00056).
     */
    protected static function extractProjectNumber(string $filename): ?string
    {
        $trimmed = trim($filename);
        if ($trimmed === '') {
            return null;
        }
        $parts = preg_split('/\s*-\s*/', $trimmed, 2);
        $first = trim($parts[0] ?? '');
        if ($first === '') {
            return null;
        }
        return $first;
    }

    /**
     * Guess upload subfolder from filename/title keywords and codes.
     */
    protected static function guessSubfolderFromTitle(string $filename, string $upper): string
    {
        $codeMatches = [];
        preg_match_all('/(?:^|[^A-Z0-9])(DTF|TRS|TRM|MIR|WIR|MTS|MS|SD|ASB|ABS|MAT|MSA|PQ|PREQ|PREQUL|MIRR)(?:[^A-Z0-9]|$)/i', $upper, $codeMatches);
        $codes = array_unique(array_map('strtoupper', $codeMatches[1] ?? []));

        if (in_array('DTF', $codes, true)) {
            return 'Document Transmittal';
        }
        if (in_array('TRS', $codes, true) || in_array('TRM', $codes, true)) {
            return 'Document Transmittal';
        }
        if (in_array('MIR', $codes, true) || in_array('MIRR', $codes, true)) {
            return 'Material Inspection Request';
        }
        if (in_array('WIR', $codes, true)) {
            return 'Work Inspection';
        }
        if (in_array('SD', $codes, true)) {
            return 'Shop Drawing';
        }
        if (in_array('MS', $codes, true) || in_array('MTS', $codes, true)) {
            return 'Method Statement';
        }
        if (in_array('ASB', $codes, true) || in_array('ABS', $codes, true)) {
            return 'As Built Drawing Submittal';
        }
        if (in_array('MAT', $codes, true)) {
            return 'Material Submittal';
        }
        if (in_array('MSA', $codes, true)) {
            return 'Material Sample';
        }
        if (in_array('PQ', $codes, true) || in_array('PREQ', $codes, true) || in_array('PREQUL', $codes, true)) {
            return 'Prequalification';
        }

        if (preg_match('/\bDTF\b|DOCUMENT\s*TRANSMITTAL|TRANSMITTAL/i', $upper)) {
            return 'Document Transmittal';
        }
        if (preg_match('/METHOD\s*STATEMENT|\bMTS\b/i', $upper)) {
            return 'Method Statement';
        }
        if (preg_match('/AS\s*BUILT/i', $upper)) {
            return 'As Built Drawing Submittal';
        }
        if (preg_match('/SHOP\s*DRAWING/i', $upper)) {
            return 'Shop Drawing';
        }
        if (preg_match('/INSPECTION\s*REQUEST|MIR\b/i', $upper)) {
            return 'Material Inspection Request';
        }
        if (preg_match('/MATERIAL\s*SUBMITTAL/i', $upper)) {
            return 'Material Submittal';
        }
        if (preg_match('/WORK\s*INSPECTION/i', $upper)) {
            return 'Work Inspection';
        }
        if (preg_match('/SAMPLE/i', $upper)) {
            return 'Material Sample';
        }
        if (preg_match('/INVOICE/i', $upper) && preg_match('/PROFORMA/i', $upper)) {
            return 'Proforma Invoice';
        }
        if (preg_match('/SUPPLIER\s*INVOICE/i', $upper)) {
            return 'Supplier Invoice';
        }
        if (preg_match('/INVOICE/i', $upper)) {
            return 'Invoice';
        }
        if (preg_match('/PAYMENT\s*VOUCHER/i', $upper)) {
            return 'Payment Voucher';
        }
        if (preg_match('/RECEIPT\s*VOUCHER/i', $upper)) {
            return 'Receipt Voucher';
        }
        if (preg_match('/CREDIT\s*NOTE/i', $upper)) {
            return 'Sales Credit Note';
        }
        if (preg_match('/DELIVERY\s*NOTE/i', $upper) && preg_match('/SUPPLIER/i', $upper)) {
            return 'Supplier Delivery Note';
        }
        if (preg_match('/TIME\s*SHEET/i', $upper)) {
            return 'Supplier Time Sheets';
        }
        if (preg_match('/BANK\s*GUA?RANTEE/i', $upper)) {
            return 'Bank Gurantees';
        }
        if (preg_match('/LETTER/i', $upper)) {
            return 'Incoming Or Outgoing Letter';
        }
        if (preg_match('/INTERNAL\s*MEMO/i', $upper)) {
            return 'Internal Memo';
        }
        if (preg_match('/KPI/i', $upper)) {
            return 'KPI Report';
        }
        if (preg_match('/MONTHLY\s*REPORT|PROGRESS\s*REPORT/i', $upper)) {
            return 'Monthly Report';
        }
        if (preg_match('/PAYMENT\s*CERTIFICATE/i', $upper)) {
            return 'Payment Certificate';
        }
        if (preg_match('/AWARD\s*NOTIFICATION/i', $upper)) {
            return 'Project Award Notification';
        }
        if (preg_match('/SNAG/i', $upper)) {
            return 'Snags';
        }
        if (preg_match('/SPARE\s*PART/i', $upper)) {
            return 'Spare Parts';
        }
        if (preg_match('/DEFECT\s*LIABILITY\s*CERTIFICATE/i', $upper)) {
            return 'Defect Liability Certificate';
        }
        if (preg_match('/ENGINEER\S*\s*CORRESPONDENCE/i', $upper)) {
            return 'Engineers Correspondences';
        }
        if (preg_match('/ENGINEER\S*\s*INSTRUCTION/i', $upper)) {
            return 'Engineers Instruction';
        }
        if (preg_match('/\bMOM\b|MINUTES\s*OF\s*MEETING/i', $upper)) {
            return 'MOM';
        }
        if (preg_match('/\bNCR\b|NON\s*CONFORMANCE/i', $upper)) {
            return 'NCR';
        }
        if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b/i', $upper)) {
            return 'Operation And Maintenance Manual';
        }
        if (preg_match('/PAYMENT\s*APPLICATION/i', $upper)) {
            return 'Payment Application';
        }
        if (preg_match('/QUALITY\s*OBSERVATION\s*REPORT/i', $upper)) {
            return 'Quality Observation Report';
        }
        if (preg_match('/REQUEST\s*FOR\s*INFORMATION|\bRFI\b/i', $upper)) {
            return 'Request For Information';
        }
        if (preg_match('/SITE\s*OBSERVATION\s*REPORT/i', $upper)) {
            return 'Site Observation Report';
        }
        if (preg_match('/SITE\s*INCIDENT\s*REPORT/i', $upper)) {
            return 'Site Incident Report';
        }
        if (preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $upper)) {
            return 'Taking Over Certificate';
        }
        if (preg_match('/TESTING\s*AND\s*COMMISSIONING/i', $upper)) {
            return 'Testing And Commissioning';
        }
        if (preg_match('/VARIATION/i', $upper)) {
            return 'Variation';
        }
        if (preg_match('/WARRANTY/i', $upper)) {
            return 'Warranty By Us';
        }
        if (preg_match('/CHANGE\s*REQUEST/i', $upper)) {
            return 'Change Request';
        }
        if (preg_match('/DESIGN\s*CALCULATION/i', $upper)) {
            return 'Design Calculation';
        }
        if (preg_match('/VERBAL\s*INSTRUCTION/i', $upper)) {
            return 'Confirmation Of Verbal Instruction';
        }
        if (preg_match('/COMMERCIAL/i', $upper)) {
            return 'Project Commercial Documents';
        }
        if (preg_match('/CATALOG/i', $upper)) {
            return 'Catalogs';
        }
        if (preg_match('/DELIVERY\s*ORDER/i', $upper)) {
            return 'Delivery Order';
        }
        if (preg_match('/ENQUIR/i', $upper)) {
            return 'Enquireis';
        }
        if (preg_match('/GOOD\s*RECEIPT\s*NOTE|\bGRN\b/i', $upper)) {
            return 'Good Receipt Note';
        }
        if (preg_match('/MATERIAL\s*ISSUE\s*NOTE|\bMIN\b/i', $upper)) {
            return 'Material Issue Note';
        }
        if (preg_match('/MATERIAL\s*RETURN\s*NOTE|\bMRN\b/i', $upper)) {
            return 'Material Return Note';
        }
        if (preg_match('/PURCHASE\s*ORDER|\bPO\b/i', $upper)) {
            return 'Purchase Order';
        }
        if (preg_match('/PURCHASE\s*REQUEST|\bPR\b/i', $upper)) {
            return 'Purchase Request';
        }
        if (preg_match('/QUOTATION/i', $upper)) {
            return 'Quotations';
        }
        if (preg_match('/SALES\s*ORDER/i', $upper)) {
            return 'Sales Order';
        }
        if (preg_match('/TRADE\s*LICENSE/i', $upper)) {
            return 'Trade License certificate';
        }
        if (preg_match('/VAT\s*REGISTRATION/i', $upper)) {
            return 'VAT Registration Certificate';
        }
        if (preg_match('/VENDOR\s*REGISTRATION/i', $upper)) {
            return 'Vendor Registration certificate';
        }
        if (preg_match('/PREQUALIFICATION/i', $upper)) {
            return 'Prequalification';
        }
        return 'Other';
    }

    /**
     * Suggest entity_id, project_id, document_category from a filename by matching project number in DB.
     */
    public static function suggestPlacement(string $filename): array
    {
        $parsed = self::parse($filename);
        $projectNumber = $parsed['project_number'];
        $category = $parsed['document_category'];

        $project = null;
        if ($projectNumber !== null) {
            $project = Project::with('entity')
                ->where('project_number', $projectNumber)
                ->first();
            if (!$project) {
                $project = Project::with('entity')
                    ->where('project_number', 'like', $projectNumber . '%')
                    ->first();
            }
        }

        return [
            'entity_id' => $project?->entity_id,
            'project_id' => $project?->id,
            'project_number' => $projectNumber,
            'document_category' => $category,
        ];
    }

    /**
     * Main sidebar folder name for a document_type value (subfolder), or null if unknown.
     */
    public static function mainFolderForDocumentType(?string $documentType): ?string
    {
        if ($documentType === null || trim($documentType) === '') {
            return null;
        }
        $trimmed = trim($documentType);
        foreach (self::sidebarFolderTree() as $mainName => $subfolders) {
            if (in_array($trimmed, $subfolders, true)) {
                return $mainName;
            }
        }

        return null;
    }

    /**
     * Human-readable folder column: "Main folder / Subfolder" using stored document_type or filename parse.
     */
    public static function folderDisplayLabel(?string $documentType, ?string $fileName = null): string
    {
        $type = $documentType !== null && trim($documentType) !== '' ? trim($documentType) : null;
        if ($type === null && $fileName !== null) {
            $parsed = self::parse($fileName);
            $cat = $parsed['document_category'] ?? null;
            $type = ($cat !== null && $cat !== '' && $cat !== 'Other') ? $cat : null;
        }
        if ($type === null) {
            return '—';
        }
        $main = self::mainFolderForDocumentType($type);

        return $main !== null ? $main.' / '.$type : $type;
    }

    /**
     * Same subfolder lists as the left navigation (see layouts.app sidebar).
     *
     * @return array<string, list<string>>
     */
    protected static function sidebarFolderTree(): array
    {
        return [
            'Financial Documents' => [
                'Bank Gurantees',
                'Invoice',
                'Payment Voucher',
                'Proforma Invoice',
                'Receipt Voucher',
                'Sales Credit Note',
                'Supplier Delivery Note',
                'Supplier Invoice',
                'Supplier Time Sheets',
            ],
            'General Correspondence' => [
                'Incoming Or Outgoing Letter',
                'Internal Memo',
                'KPI Report',
                'Monthly Report',
                'Payment Certificate',
                'Project Award Notification',
                'Snags',
                'Spare Parts',
            ],
            'Project Correspondence' => [
                'Defect Liability Certificate',
                'Engineers Correspondences',
                'Engineers Instruction',
                'MOM',
                'NCR',
                'Operation And Maintenance Manual',
                'Payment Application',
                'Quality Observation Report',
                'Request For Information',
                'Site Observation Report',
                'Site Incident Report',
                'Taking Over Certificate',
                'Testing And Commissioning',
                'Variation',
                'Warranty By Us',
                'Change Request',
                'Design Calculation',
                'Confirmation Of Verbal Instruction',
                'Project Commercial Documents',
            ],
            'Purchase Documents' => [
                'Catalogs',
                'Delivery Order',
                'Enquireis',
                'Good Receipt Note',
                'Material Issue Note',
                'Material Return Note',
                'Purchase Order',
                'Purchase Request',
                'Quotations',
                'Sales Order',
                'Trade License certificate',
                'VAT Registration Certificate',
                'Vendor Registration certificate',
            ],
            'Transmittals Documents' => [
                'As Built Drawing Submittal',
                'Material Submittal',
                'Material Inspection Request',
                'Method Statement',
                'Prequalification',
                'Shop Drawing',
                'Work Inspection',
                'Document Transmittal',
                'Material Sample',
            ],
        ];
    }
}
