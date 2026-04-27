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
     * Classify folder from first-page OCR/text using reference headings (many label/value formats) plus title keywords.
     */
    public static function guessSubfolderFromDocumentText(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return 'Other';
        }

        $window = self::normalizeOcrText($text);
        $window = substr($window, 0, 14000);
        $synthetic = self::extractReferenceSyntheticTitle($window);
        $synthetic = trim($synthetic);

        if ($synthetic !== '') {
            $cat = self::guessSubfolderFromTitle($synthetic, strtoupper($synthetic));
            if ($cat !== 'Other') {
                return $cat;
            }
        }

        $base = substr($window, 0, 6000);

        return self::guessSubfolderFromTitle($base, strtoupper($base));
    }

    /**
     * Normalize OCR output for safer keyword/code matching across noisy layouts.
     */
    protected static function normalizeOcrText(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace('/[ \t]+/', ' ', $normalized) ?? $normalized;

        // Join OCR outputs like "M A T E R I A L S" into "MATERIALS".
        $normalized = preg_replace_callback('/\b(?:[A-Za-z]\s+){2,}[A-Za-z]\b/', function (array $m): string {
            return str_replace(' ', '', $m[0]);
        }, $normalized) ?? $normalized;

        $normalized = preg_replace('/\n{3,}/', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Extract reference number and subject/title using OCR text first, then filename fallback.
     *
     * @return array{reference_no:string, subject:string}
     */
    public static function extractReferenceAndSubject(?string $ocrText, string $fileName): array
    {
        $referenceNo = '';
        $subject = '';

        $ocr = trim((string) $ocrText);
        if ($ocr !== '') {
            $normalized = self::normalizeOcrText($ocr);

            $referencePatterns = [
                '/(?:^|\n)\s*REF(?:ERENCE)?\s*\.?\s*(?:NO\.?|NUMBER)?\s*[:\-]\s*([^\n]{3,180})/i',
                '/(?:^|\n)\s*SUBMITTAL\s*(?:NO\.?|NUMBER)\s*[:\-]\s*([^\n]{3,180})/i',
                '/(?:^|\n)\s*SUBMITTAL\s*(?:NR|NO|NUMBER)\.?\s*[:\-]\s*([^\n]{3,180})/i',
                '/(?:^|\n)\s*MIR\s*NO\.?\s*[:\-]\s*([^\n]{3,180})/i',
                '/(?:^|\n)\s*DOC(?:UMENT)?\s*TRANS(?:\.|MITTAL)?\s*NO\.?\s*[:\-]\s*([^\n]{3,180})/i',
                '/(?:^|\n)\s*DRAWING\s*REF(?:ERENCE)?\.?\s*[:\-]\s*([^\n]{3,180})/i',
                '/(?:^|\n)\s*DRAWING\s*REF\.?\s*[:\-]\s*([^\n]{3,180})/i',
            ];
            foreach ($referencePatterns as $re) {
                if (preg_match($re, $normalized, $m)) {
                    $candidate = trim(preg_replace('/\s+/', ' ', (string) $m[1]));
                    $candidate = preg_replace('/\s*(?:REV(?:ISION)?|DATE)\s*[:\-].*$/i', '', $candidate) ?? $candidate;
                    if ($candidate !== '') {
                        $referenceNo = trim($candidate);
                        break;
                    }
                }
            }

            $subjectPatterns = [
                // Multiline-friendly: capture current line and up to 2 continuation lines
                // until another label-like line appears.
                // Priority: Material Description should win over generic Submittal Title.
                '/(?:^|\n)\s*MATERIAL\s*DESCRIPTION\s*[:\-]?\s*([^\n]{5,240}(?:\n(?!\s*[A-Z][A-Z0-9\s\/().-]{1,30}\s*:)[^\n]{1,220}){0,2})/i',
                '/(?:^|\n)\s*MATERIALS?\s+FOR\s+INSPECTION\s*[:\-]?\s*([^\n]{5,240}(?:\n(?!\s*[A-Z][A-Z0-9\s\/().-]{1,30}\s*:)[^\n]{1,220}){0,2})/i',
                '/(?:^|\n)\s*SUBMITTAL\s*TITLE\s*[:\-]\s*([^\n]{5,240}(?:\n(?!\s*[A-Z][A-Z0-9\s\/().-]{1,30}\s*:)[^\n]{1,220}){0,2})/i',
                '/(?:^|\n)\s*SUBJECT\s*[:\-]\s*([^\n]{5,240}(?:\n(?!\s*[A-Z][A-Z0-9\s\/().-]{1,30}\s*:)[^\n]{1,220}){0,2})/i',
                '/(?:^|\n)\s*DESCRIPTION\s*[:\-]\s*([^\n]{5,240}(?:\n(?!\s*[A-Z][A-Z0-9\s\/().-]{1,30}\s*:)[^\n]{1,220}){0,2})/i',
            ];
            foreach ($subjectPatterns as $re) {
                if (preg_match($re, $normalized, $m)) {
                    $candidate = trim(preg_replace('/\s+/', ' ', (string) $m[1]));
                    $candidate = preg_replace('/\s*(?:REV(?:ISION)?|DATE)\s*[:\-].*$/i', '', $candidate) ?? $candidate;
                    if ($candidate !== '') {
                        $subject = trim($candidate);
                        break;
                    }
                }
            }
        }

        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        if ($referenceNo === '' || $subject === '') {
            if (preg_match('/^\s*([A-Z0-9]+(?:-[A-Z0-9]+){1,})\s*-\s*(.+)\s*$/iu', $nameWithoutExt, $m)
                || preg_match('/^\s*([A-Z0-9]+(?:-[A-Z0-9]+){1,})[ _]+(.+)\s*$/iu', $nameWithoutExt, $m)) {
                if ($referenceNo === '') {
                    $referenceNo = trim((string) ($m[1] ?? ''));
                }
                if ($subject === '') {
                    $subject = trim((string) ($m[2] ?? ''));
                }
            }
        }

        $referenceNo = $referenceNo !== '' ? $referenceNo : $nameWithoutExt;
        $subject = $subject !== '' ? $subject : '—';

        $isRevisionTail = (bool) preg_match('/^(?:REV(?:ISION)?\s*)?[A-Z]?\d{1,3}(?:[._-][A-Z0-9]+)*(?:\s*\(\d+\))?$/i', $subject);
        if ($isRevisionTail) {
            $subject = '—';
        }
        // Ignore generic headers used as form titles, not real subject lines.
        if (preg_match('/^MATERIAL\s+SUBMITTAL$/i', trim($subject))) {
            $subject = '—';
        }

        return [
            'reference_no' => $referenceNo,
            'subject' => $subject,
        ];
    }

    /**
     * Pull reference-like strings from typical title-block labels so codes (DTF, MST, MS, …) can be detected.
     */
    protected static function extractReferenceSyntheticTitle(string $window): string
    {
        $parts = [];
        $labelPatterns = [
            '/REFERENCE\s+NUMBER\s*[:\s]+([^\r\n]{1,220})/ui',
            '/REF(?:ERENCE)?\s*\.?\s*NO\.?\s*[:\s]+([^\r\n]{1,220})/ui',
            '/SUBMITTAL\s+(?:NO\.?|REFERENCE)\s*[:\s]+([^\r\n]{1,220})/ui',
            '/SUBMITTAL\s+REFERENCE\s*[:\s]+([^\r\n]{1,220})/ui',
            '/METHOD\s+STATEMENT\s+NO\.?\s*[:\s]+([^\r\n]{1,220})/ui',
            '/DOC(?:UMENT)?\.?\s*TRANS(?:\.|MITTAL)?\s*NO\.?\s*[:\s]+([^\r\n]{1,220})/ui',
            '/\bREF\s*:\s*([^\r\n]{1,220})/ui',
            '/\bREF\s*\.?\s*NO\.?\s*[:\s]+([^\r\n]{1,220})/ui',
            '/PREVIOUS\s+SUBMITTAL\s+REF\s*NO\.?\s*[:\s]+([^\r\n]{1,220})/ui',
        ];

        foreach ($labelPatterns as $re) {
            if (preg_match_all($re, $window, $m)) {
                foreach ($m[1] as $cap) {
                    $cap = trim(preg_replace('/\s+/', ' ', (string) $cap));
                    if ($cap !== '') {
                        $parts[] = $cap;
                    }
                }
            }
        }

        $merged = implode(' ', array_unique($parts));
        if (strlen($merged) < 24) {
            $merged = trim($merged . ' ' . substr($window, 0, 3500));
        } else {
            $merged = trim($merged . ' ' . substr($window, 0, 1200));
        }

        return $merged;
    }

    /**
     * Filename parse plus first-page text: OCR wins for category when it finds a non-Other type.
     *
     * @return array{entity_id: ?int, project_id: ?int, project_number: ?string, document_category: string, category_source: string}
     */
    public static function suggestPlacementMerged(string $filename, ?string $ocrText): array
    {
        $fromFile = self::suggestPlacement($filename);
        $fileCategory = $fromFile['document_category'] ?? 'Other';

        $contentCategory = 'Other';
        if ($ocrText !== null && trim($ocrText) !== '') {
            $contentCategory = self::guessSubfolderFromDocumentText($ocrText);
        }

        $category = $fileCategory;
        $source = 'filename';

        if ($contentCategory !== 'Other') {
            $category = $contentCategory;
            $source = 'ocr';
        } elseif ($fileCategory !== 'Other') {
            $category = $fileCategory;
            $source = 'filename';
        } else {
            $category = 'Other';
            $source = 'none';
        }

        return [
            'entity_id' => $fromFile['entity_id'],
            'project_id' => $fromFile['project_id'],
            'project_number' => $fromFile['project_number'],
            'document_category' => $category,
            'category_source' => $source,
        ];
    }

    /**
     * Automation-oriented classifier with confidence score.
     * Uses OCR content first (best for mixed PDF formats), then filename as fallback.
     *
     * @return array{
     *   document_category:string,
     *   category_source:string,
     *   confidence:float,
     *   reference_no:string,
     *   subject:string
     * }
     */
    public static function classifyForAutomation(string $filename, ?string $ocrText): array
    {
        $fileCategory = self::parse($filename)['document_category'] ?? 'Other';
        $ocr = trim((string) $ocrText);
        $contentCategory = $ocr !== '' ? self::guessSubfolderFromDocumentText($ocr) : 'Other';

        $category = 'Other';
        $source = 'none';
        $confidence = 0.10;

        if ($contentCategory !== 'Other') {
            $category = $contentCategory;
            $source = 'ocr';
            $confidence = 0.86;
        } elseif ($fileCategory !== 'Other') {
            $category = $fileCategory;
            $source = 'filename';
            $confidence = $ocr !== '' ? 0.45 : 0.60;
        }

        // Agreement between OCR and filename boosts certainty.
        if ($contentCategory !== 'Other' && $fileCategory !== 'Other' && $contentCategory === $fileCategory) {
            $confidence = 0.95;
        }

        // OCR/file disagreement: keep OCR decision but reduce confidence.
        if ($contentCategory !== 'Other' && $fileCategory !== 'Other' && $contentCategory !== $fileCategory) {
            $confidence = 0.74;
        }

        $meta = self::extractReferenceAndSubject($ocrText, $filename);

        return [
            'document_category' => $category,
            'category_source' => $source,
            'confidence' => round($confidence, 2),
            'reference_no' => $meta['reference_no'] ?? '—',
            'subject' => $meta['subject'] ?? '—',
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
        // Keep As-Built docs out of Method Statement even if code contains "-MS-".
        if (preg_match('/\bAS[\s\-]*BUILT\b|\bASBUILT\b/i', $upper)) {
            return 'As Built Drawing Submittal';
        }

        $codeMatches = [];
        preg_match_all('/(?:^|[^A-Z0-9])(DTF|DT|TRS|TRM|MIR|WIR|MTS|MST|MSS|MOS|MS|MT|SD|DWG|ASB|ABS|AB|MAT|MSA|MAS|MB|PQ|PREQ|PREQUL|MIRR)(?:[^A-Z0-9]|$)/i', $upper, $codeMatches);
        $codes = array_unique(array_map('strtoupper', $codeMatches[1] ?? []));
        $hasPrequalificationKeyword = (bool) preg_match('/PRE[\s\-]*QUALIF(?:ICATION|ICATIONS)?|\bPREQUAL\b|\bPREQ\b/i', $upper);
        $hasMethodKeyword = (bool) preg_match('/METHOD\s*STATEMENT|METHOD\s+OF\s+STATEMENT|METHOD\s*ST(?:\.|ATEMENT)?|STATEMENT\s+SUBMITTAL|\bMTS\b|\bMST\b|\bMSS\b|\bMOS\b/i', $upper);
        $hasMaterialSubmittalKeyword = (bool) preg_match('/MATERIAL\s*(?:TECHNICAL\s*)?SUBMITTAL|SUBMITTAL\s+TITLE\s*[:\-]\s*MATERIAL/i', $upper);
        $hasStrongMethodCode = in_array('MST', $codes, true)
            || in_array('MTS', $codes, true)
            || in_array('MSS', $codes, true)
            || in_array('MOS', $codes, true)
            || in_array('MT', $codes, true);

        // In mixed check-list OCR forms, "Work Method Statement" may appear as an unselected option.
        // If prequalification is present and there is no strong method code, prefer Prequalification.
        if ($hasPrequalificationKeyword && (!$hasMethodKeyword || !$hasStrongMethodCode)) {
            return 'Prequalification';
        }

        // Prefer Material Submittal when explicit material indicators exist.
        // This prevents OCR legend text like "SD = Shop Drawings" from overriding
        // actual "Submittal Title: Material Submittal ...".
        if ($hasMaterialSubmittalKeyword || in_array('MAT', $codes, true) || in_array('MB', $codes, true)) {
            return 'Material Submittal';
        }

        if (in_array('DTF', $codes, true)) {
            return 'Document Transmittal';
        }
        if (in_array('DT', $codes, true) || in_array('TRS', $codes, true) || in_array('TRM', $codes, true)) {
            return 'Document Transmittal';
        }
        if (in_array('MIR', $codes, true) || in_array('MIRR', $codes, true)) {
            return 'Material Inspection Request';
        }
        if (in_array('WIR', $codes, true)) {
            return 'Work Inspection';
        }
        if (in_array('ASB', $codes, true) || in_array('ABS', $codes, true) || in_array('AB', $codes, true)) {
            return 'As Built Drawing Submittal';
        }
        if (in_array('SD', $codes, true)) {
            return 'Shop Drawing';
        }
        if (in_array('DWG', $codes, true)) {
            return 'Shop Drawing';
        }
        if (in_array('MST', $codes, true) || in_array('MSS', $codes, true) || in_array('MOS', $codes, true)) {
            return 'Method Statement';
        }
        if (in_array('MTS', $codes, true) || in_array('MT', $codes, true)) {
            return 'Method Statement';
        }
        if (in_array('MAT', $codes, true)) {
            return 'Material Submittal';
        }
        if (in_array('MSA', $codes, true) || in_array('MAS', $codes, true)) {
            return 'Material Sample';
        }
        if (in_array('MB', $codes, true)) {
            return 'Material Submittal';
        }
        if (in_array('MS', $codes, true)) {
            if (preg_match('/METHOD\s*STATEMENT|METHOD\s+OF\s+STATEMENT|METHOD\s*ST(?:\.|ATEMENT)?|STATEMENT\s+SUBMITTAL/i', $upper)) {
                return 'Method Statement';
            }
            return 'Material Submittal';
        }
        if (in_array('PQ', $codes, true) || in_array('PREQ', $codes, true) || in_array('PREQUL', $codes, true)) {
            return 'Prequalification';
        }

        if (preg_match('/\bDTF\b|\bDT\b|DOC\.?\s*TRANS|DOCUMENT\s*TRANSMITTAL|TRANSMITTAL/i', $upper)) {
            return 'Document Transmittal';
        }
        if (preg_match('/METHOD\s*STATEMENT|METHOD\s+OF\s+STATEMENT|METHOD\s*ST(?:\.|ATEMENT)?|STATEMENT\s+SUBMITTAL|\bMTS\b|\bMST\b|\bMSS\b|\bMOS\b/i', $upper)) {
            return 'Method Statement';
        }
        if (preg_match('/AS\s*BUILT/i', $upper)) {
            return 'As Built Drawing Submittal';
        }
        if (preg_match('/SHOP\s*DRAWING|\bDWG\b/i', $upper)) {
            return 'Shop Drawing';
        }
        if (preg_match('/INSPECTION\s*REQUEST|MIR\b/i', $upper)) {
            return 'Material Inspection Request';
        }
        if (preg_match('/MATERIAL\s*SUBMITTAL|\bMAT(?:ERIAL)?\s*SUB(?:MITTAL)?\b/i', $upper)) {
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
    public static function sidebarFolderTree(): array
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
