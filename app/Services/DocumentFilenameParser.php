<?php

namespace App\Services;

use App\Models\Project;

class DocumentFilenameParser
{
    /** Subfolders used in left navigation/upload placement */
    protected static array $subfolders = [
        'Bank Gurantees',
        'BOQ Bill Of Quantities',
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
        'Permit and NOC',
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
        'Design Calculation',
        'Confirmation Of Verbal Instruction',
        'Project Technical Documents',
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
     * Classify folder from first-page OCR/text: title/header, then subject, then body heuristics.
     * Filename is merged separately in classifyForAutomation / suggestPlacementMerged.
     */
    public static function guessSubfolderFromDocumentText(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return 'Other';
        }

        $normalized = self::normalizeOcrText($text);
        // T&C often appears late ("Content of Request"); scan full OCR, not only first 14k chars.
        if (self::textLooksLikeTestingAndCommissioning($normalized)) {
            return 'Testing And Commissioning';
        }
        if (self::textLooksLikeTestingAndCommissioningLoose($normalized)) {
            return 'Testing And Commissioning';
        }
        if (preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $normalized)) {
            return 'Taking Over Certificate';
        }
        if (preg_match('/ENGINEER\S*\s*INSTRUCTION|(?:^|[^A-Z0-9])EI(?:[^A-Z0-9]|$)/i', $normalized)) {
            return 'Engineers Instruction';
        }
        if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/i', $normalized)) {
            return 'Operation And Maintenance Manual';
        }
        if (self::textLooksLikePaymentApplication($normalized)) {
            return 'Payment Application';
        }
        if (preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $normalized)) {
            return 'Request For Information';
        }
        if (self::textLooksLikeMaterialSubmittal($normalized)) {
            return 'Material Submittal';
        }
        if (preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $normalized)) {
            return 'BOQ Bill Of Quantities';
        }
        $window = substr($normalized, 0, 14000);

        // OCR order: title/header first, then explicit subject (filename handled in classifyForAutomation).
        $titleCategory = self::detectCategoryFromTitle($window);
        if ($titleCategory !== 'Other') {
            return $titleCategory;
        }
        $subjectCategory = self::detectCategoryFromSubject($window);
        if ($subjectCategory !== 'Other') {
            return $subjectCategory;
        }

        if (preg_match('/PROJECT\s+AWARD\s+NOTIFICATION|\(\s*PAN\s*\)/iu', $window)) {
            return 'Project Award Notification';
        }
        if (preg_match('/PAYMENT\s*CERTI(?:FICATE|ACATE)|SUBCONTRACTOR\s*PAYMENT\s*CERTI|CERTIFICATE\s*NO\.?\s*[:\-]/iu', $window)) {
            return 'Payment Certificate';
        }
        if (preg_match('/\bNO\s+OBJECTION\b|(?:^|[^A-Z0-9])NOC(?:[^A-Z0-9]|$)|WATER\s+NOC|ELECTRIC(?:ITY)?\s+NOC/iu', $window)) {
            return 'Permit and NOC';
        }
        if (preg_match('/\bPERMIT\b|BUILDING\s+PERMIT|WORK\s+PERMIT|CONSTRUCTION\s+PERMIT/iu', $window)) {
            return 'Permit and NOC';
        }
        $synthetic = self::extractReferenceSyntheticTitle($window);
        $synthetic = trim($synthetic);

        if ($synthetic !== '') {
            $cat = self::guessSubfolderFromTitle($synthetic, strtoupper($synthetic));
            // Synthetic string usually starts with REF/WIR; do not let it hide T&C / incident / etc. in the same OCR window.
            if ($cat !== 'Other' && $cat !== 'Incoming Or Outgoing Letter') {
                if (self::textLooksLikeTestingAndCommissioning($normalized)) {
                    return 'Testing And Commissioning';
                }
                if (self::textLooksLikeTestingAndCommissioningLoose($normalized)) {
                    return 'Testing And Commissioning';
                }
                if (preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $normalized)) {
                    return 'Taking Over Certificate';
                }
                if (preg_match('/ENGINEER\S*\s*INSTRUCTION|(?:^|[^A-Z0-9])EI(?:[^A-Z0-9]|$)/i', $normalized)) {
                    return 'Engineers Instruction';
                }
                if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/i', $normalized)) {
                    return 'Operation And Maintenance Manual';
                }
                if (self::textLooksLikePaymentApplication($normalized)) {
                    return 'Payment Application';
                }
                if (preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $normalized)) {
                    return 'Request For Information';
                }
                if (preg_match('/SITE[\s\-]*INCIDENT[\s\-]+REPORT|INCIDENT[\s\-]+(?:REPORT|RERPORT)/i', $window)) {
                    return 'Site Incident Report';
                }
                return $cat;
            }
        }

        $base = substr($window, 0, 6000);
        if (self::looksLikeInternalMemo($base)) {
            return 'Internal Memo';
        }
        $reportFromSubject = self::detectReportFromSubject($base);
        if ($reportFromSubject !== null) {
            return $reportFromSubject;
        }
        if (self::looksLikeProjectLetter($base)) {
            return 'Incoming Or Outgoing Letter';
        }

        return self::guessSubfolderFromTitle($base, strtoupper($base));
    }

    /**
     * Classify from OCR title/subject/report lines only (no full-body keyword fallbacks).
     * Used to override filename register codes (e.g. WIR) when the first-page heading disagrees.
     */
    protected static function guessSubfolderFromOcrHeadingsOnly(string $text): string
    {
        $normalized = self::normalizeOcrText($text);
        if (self::textLooksLikeTestingAndCommissioning($normalized)) {
            return 'Testing And Commissioning';
        }
        if (self::textLooksLikeTestingAndCommissioningLoose($normalized)) {
            return 'Testing And Commissioning';
        }
        if (preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $normalized)) {
            return 'Taking Over Certificate';
        }
        if (preg_match('/ENGINEER\S*\s*INSTRUCTION|(?:^|[^A-Z0-9])EI(?:[^A-Z0-9]|$)/i', $normalized)) {
            return 'Engineers Instruction';
        }
        if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/i', $normalized)) {
            return 'Operation And Maintenance Manual';
        }
        if (self::textLooksLikePaymentApplication($normalized)) {
            return 'Payment Application';
        }
        if (preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $normalized)) {
            return 'Request For Information';
        }
        if (self::textLooksLikeMaterialSubmittal($normalized)) {
            return 'Material Submittal';
        }
        if (preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $normalized)) {
            return 'BOQ Bill Of Quantities';
        }
        $window = substr($normalized, 0, 14000);
        $cat = self::detectCategoryFromTitle($window);
        if ($cat !== 'Other') {
            return $cat;
        }
        $cat = self::detectCategoryFromSubject($window);
        if ($cat !== 'Other') {
            return $cat;
        }
        $reportFromSubject = self::detectReportFromSubject($window);
        if ($reportFromSubject !== null) {
            return $reportFromSubject;
        }

        return 'Other';
    }

    protected static function detectCategoryFromTitle(string $window): string
    {
        if (self::textLooksLikeTestingAndCommissioningLoose($window)) {
            return 'Testing And Commissioning';
        }
        if (preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $window)) {
            return 'Taking Over Certificate';
        }
        if (preg_match('/ENGINEER\S*\s*INSTRUCTION|(?:^|[^A-Z0-9])EI(?:[^A-Z0-9]|$)/i', $window)) {
            return 'Engineers Instruction';
        }
        if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/i', $window)) {
            return 'Operation And Maintenance Manual';
        }
        if (self::textLooksLikePaymentApplication($window)) {
            return 'Payment Application';
        }
        if (preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $window)) {
            return 'Request For Information';
        }
        if (self::textLooksLikeMaterialSubmittal($window)) {
            return 'Material Submittal';
        }
        if (preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $window)) {
            return 'BOQ Bill Of Quantities';
        }
        // Title scan must cover multi-row forms (REF block + "Content of request" lower on page).
        $scan = strtoupper(substr($window, 0, min(20000, strlen($window))));
        $lines = preg_split('/\n+/u', $scan) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strlen($line) < 4) {
                continue;
            }
            // Skip ref/date rows; focus on heading-like rows.
            if (preg_match('/^(DATE|REF|REFERENCE|PROJECT|CLIENT|TO|ATTENTION)\b/u', $line)) {
                continue;
            }
            if (!preg_match('/REPORT|MEMO|NOTIFICATION|CERTI(?:FICATE|ACATE)|SUBMITTAL|STATEMENT|INSPECTION|TRANSMITTAL|INVOICE|VOUCHER|REQUEST|DEFECT|LIABILITY|\bDLC\b|VARIATION|COST\s+VARIATION|DESIGN\s+CHANGE|\bCVI\b|\bQOR\b|\bSOR\b|\bSON\b|INCIDENT|TESTING|COMMISSION|TAKING|\bTOC\b|ENGINEER|INSTRUCTION|\bEI\b|OPERATION|MAINTENANCE|\bOMM\b|O\s*&\s*M|PAYMENT|APPLICATION|INTERIM|INFORMATION|\bRFI\b|\bBOQ\b|BILL\s+OF\s+QUANTITIES?|\bPTD\b|PROJECT\s+TECHNICAL|\bNOC\b|NO\s+OBJECTION|\bPERMIT\b/u', $line)) {
                continue;
            }
            $cat = self::guessSubfolderFromTitle($line, $line);
            if ($cat !== 'Other') {
                return $cat;
            }
        }

        return 'Other';
    }

    protected static function detectCategoryFromSubject(string $window): string
    {
        if (preg_match('/(?:^|\n)\s*SUBJECT\s*[:\-]\s*([^\n]{4,260})/iu', $window, $m)) {
            $subject = trim((string) ($m[1] ?? ''));
            if ($subject !== '') {
                $upper = strtoupper($subject);
                if (preg_match('/\bSNAG(?:S|GING)?\b/u', $upper)) {
                    return 'Snags';
                }
                if (preg_match('/DEFECTS?\s+LIABILITY\s+CERTIFICATE|\bDLC\b|REQUEST\s+FOR\s+DEFECTS?\s+LIABILITY/i', $upper)) {
                    return 'Defect Liability Certificate';
                }
                if (preg_match('/\bVARIATION\b|COST\s+VARIATION|VARIATION\s+FOR|DESIGN\s+CHANGE.*?VARIATION/i', $upper)) {
                    return 'Variation';
                }
                if (preg_match('/(?:^|[^A-Z0-9])CVI(?:[^A-Z0-9]|$)/i', $upper)) {
                    return 'Confirmation Of Verbal Instruction';
                }
                if (preg_match('/(?:^|[^A-Z0-9])QOR(?:[^A-Z0-9]|$)/i', $upper)) {
                    return 'Quality Observation Report';
                }
                if (preg_match('/(?:^|[^A-Z0-9])(?:SOR|SON)(?:[^A-Z0-9]|$)/i', $upper)) {
                    return 'Site Observation Report';
                }
                if (preg_match('/SITE[\s\-]*INCIDENT[\s\-]+REPORT|INCIDENT[\s\-]+(?:REPORT|RERPORT)/i', $upper)) {
                    return 'Site Incident Report';
                }
                if (preg_match('/ENGINEER\S*\s*INSTRUCTION|(?:^|[^A-Z0-9])EI(?:[^A-Z0-9]|$)/i', $upper)) {
                    return 'Engineers Instruction';
                }
                if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/i', $upper)) {
                    return 'Operation And Maintenance Manual';
                }
                if (self::textLooksLikePaymentApplication($upper)) {
                    return 'Payment Application';
                }
                if (preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $upper)) {
                    return 'Request For Information';
                }
                if (self::textLooksLikeMaterialSubmittal($subject)) {
                    return 'Material Submittal';
                }
                if (preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $upper)) {
                    return 'BOQ Bill Of Quantities';
                }
                if (preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $upper)) {
                    return 'Taking Over Certificate';
                }
                if (self::textLooksLikeTestingAndCommissioning($upper)) {
                    return 'Testing And Commissioning';
                }
                if (self::textLooksLikeTestingAndCommissioningLoose($subject)) {
                    return 'Testing And Commissioning';
                }
                $cat = self::guessSubfolderFromTitle($subject, $upper);
                if ($cat !== 'Other') {
                    return $cat;
                }
            }
        }

        return 'Other';
    }

    /**
     * Prefer explicit report subjects over generic letter framing.
     */
    protected static function detectReportFromSubject(string $text): ?string
    {
        $upper = strtoupper($text);
        if (preg_match('/(?:^|\n)\s*SUBJECT\s*[:\-]\s*.*\bKPI\b.*$/mi', $upper)) {
            return 'KPI Report';
        }
        if (preg_match('/(?:^|\n)\s*SUBJECT\s*[:\-]\s*.*\b(?:MONTHLY|PROGRESS|MPR)\b.*\bREPORT\b.*$/mi', $upper)) {
            return 'Monthly Report';
        }

        return null;
    }

    /**
     * Memos share TO/REF/DATE/SUBJECT blocks with project letters; detect memo first.
     */
    protected static function looksLikeInternalMemo(string $text): bool
    {
        $upper = strtoupper($text);
        if (preg_match('/\bINTERNAL\s+MEMO\b|\bINTER\s*[- ]OFFICE\s+MEMO\b|\bMEMORANDUM\b|^\s*MEMO\s*$/m', $upper)) {
            return true;
        }
        if (preg_match('/\b[A-Z]{2,}(?:-[A-Z]{2,})*-MEM-\d+/u', $upper)) {
            return true;
        }
        // Some memo templates use IMOxxxx/SM/KK/yy reference numbers without the word MEM.
        if (preg_match('/\bIMO\d{3,5}\/SM\/[A-Z]{2}\/\d{2,4}\b/u', $upper)) {
            return true;
        }
        if (preg_match('/(^|\n)\s*MEMO\s*(?:\n|$)/u', $upper)) {
            return true;
        }

        return false;
    }

    /**
     * Detect common project-letter structure from OCR text.
     * Requires multiple markers to avoid false positives.
     */
    protected static function looksLikeProjectLetter(string $text): bool
    {
        $upper = strtoupper($text);
        if (self::looksLikeInternalMemo($text)) {
            return false;
        }
        if (preg_match('/PROJECT\s+AWARD\s+NOTIFICATION|\(\s*PAN\s*\)/u', $upper)) {
            return false;
        }
        if (preg_match('/DOCUMENT\s+TRANSMITTAL|TRANSMITTAL\s+NOTE|\bDTF\b|\bTRS\b|\bTRM\b/u', $upper)) {
            return false;
        }
        if (self::textLooksLikePaymentApplication($text)) {
            return false;
        }

        // Submittal/inspection forms also carry TO/DATE/REF/SUBJECT label cells in
        // their title block; without this guard those forms get mis-classified as
        // Letters. Anything that explicitly says it's one of those documents wins.
        $submittalHeader = '/SHOP\s*DRAWING(?:\s*SUBMITTAL)?'
            . '|MATERIAL\s*(?:TECHNICAL\s*)?SUBMITTAL'
            . '|METHOD\s*STATEMENT|STATEMENT\s+SUBMITTAL'
            . '|MATERIAL\s*INSPECTION\s*REQUEST|\bMIR\b'
            . '|WORK\s*INSPECTION\s*REQUEST|\bWIR\b'
            . '|ENGINEER\S*\s*INSTRUCTION|(?:^|[^A-Z0-9])EI(?:[^A-Z0-9]|$)'
            . '|QUALITY\s*OBSERVATION\s*REPORT|\bQOR\b'
            . '|SITE\s*OBSERVATION\s*REPORT|\bSOR\b|\bSON\b'
            . '|SITE\s*INCIDENT\s*REPORT|INCIDENT\s+(?:REPORT|RERPORT)'
            . '|TESTING\s+(?:AND|AS)\s+COMM|TESTING\s+AND\s+COMMISSION'
            . '|TAKING\s*OVER\s*CERTIFICATE|\bTOC\b'
            . '|OPERATION\s+AND\s+MAINTENANCE|\bO\s*&\s*M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)'
            . '|PAYMENT\s*APPLICATION|APPLICATION\s+FOR\s+(?:INTERIM\s+)?(?:PAYMENT|PAYMENTS)|INTERIM\s+PAYMENT\s+APPLICATION|REQUEST\s+FOR\s+(?:INTERIM\s+)?PAYMENT'
            . '|REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)'
            . '|(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b'
            . '|PRE[\s-]*QUALIF(?:ICATION|ICATIONS)?|\bPREQUAL\b'
            . '|AS[\s-]*BUILT(?:\s+DRAWING)?\s*SUBMITTAL?'
            . '|MATERIAL\s*SAMPLE'
            . '/u';
        if (preg_match($submittalHeader, $upper)) {
            return false;
        }

        $score = 0;
        $score += preg_match('/(?:^|\n)\s*(?:OUR\s+)?REF(?:ERENCE)?\s*[:\-]/u', $upper) ? 1 : 0;
        $score += preg_match('/(?:^|\n)\s*DATE\s*[:\-]/u', $upper) ? 1 : 0;
        $score += preg_match('/(?:^|\n)\s*(?:TO|ATTENTION)\s*[:\-]/u', $upper) ? 1 : 0;
        $score += preg_match('/(?:^|\n)\s*SUBJECT\s*[:\-]/u', $upper) ? 1 : 0;
        $score += preg_match('/\bDEAR\s+(?:SIR|MADAM|MR|MRS|MS)\b/u', $upper) ? 1 : 0;

        return $score >= 2;
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

        // Tesseract often misreads "Testing and Commissioning" (AND→AS; broken COMMISSIONING).
        $tncOcrFixes = [
            '/\bTESTIN\s+GD\s+COMIISONIN\s+GI\b/iu' => 'TESTING AND COMMISSIONING',
            '/\bTESTIN\s+G\s+D\s+COMIISONIN\s+GI\b/iu' => 'TESTING AND COMMISSIONING',
            '/\bTESTING\s+AND\s+COMISSIONING\b/iu' => 'TESTING AND COMMISSIONING',
            '/\bTESTING\s+AND\s+COMI{1,2}S+ION(?:I?NG|ING)\b/iu' => 'TESTING AND COMMISSIONING',
            '/\bTESTING\s+AS\s+COMMISIOSNIN\s+G\b/iu' => 'TESTING AND COMMISSIONING',
            '/\bTESTING\s+AS\s+COMM[I1]SSIONING\b/iu' => 'TESTING AND COMMISSIONING',
            '/\bTESTING\s+AS\s+COMMIS+ION(?:I?NG|ING)\b/iu' => 'TESTING AND COMMISSIONING',
            '/\bTESTING\s+&\s+COMMISSIONING\b/iu' => 'TESTING AND COMMISSIONING',
        ];
        foreach ($tncOcrFixes as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
        }

        return trim($normalized);
    }

    /**
     * Detect "Testing and Commissioning" including common OCR garble (e.g. AND→AS, COMMISSIONING split/jumbled).
     */
    protected static function textLooksLikeTestingAndCommissioning(string $upper): bool
    {
        if (preg_match('/TESTING\s+AND\s+COMMISS?IO?N[I1]?(?:ING)?/i', $upper)) {
            return true;
        }
        // Source PDF or OCR typo: one M, double S ("comissioning").
        if (preg_match('/TESTING\s+AND\s+COMI{1,2}SSIONING\b/i', $upper)) {
            return true;
        }
        // AND misread as AS, then "COMMISSIONING" mangled but still COMM… ending NG or space+G.
        if (preg_match('/TESTING\s+AS\s+COMM(?!ERCIAL)(?:[A-Z]{6,22}\s+G|[A-Z]{10,24}G)\b/i', $upper)) {
            return true;
        }
        // Heavy garble: "TESTIN GD COMIISONIN GI", "TESTIN G D COM …", letters dropped/spurious spaces.
        if (preg_match('/TESTIN\s*G?\s*D\s+COM[I1]{2,}[EOSNI]{2,14}(?:\s+GI\b|\s+G\s*I\b|ING\b|NG\b)/i', $upper)) {
            return true;
        }
        if (preg_match('/TESTIN(?:G)?\s+GD\s+COM[I1EOSN]{6,20}(?:\s+GI|\s+G\s+I)\b/i', $upper)) {
            return true;
        }

        return false;
    }

    /**
     * Last-resort T&C detection when strict patterns miss (weak OCR, odd spacing, line breaks).
     */
    protected static function textLooksLikeTestingAndCommissioningLoose(string $text): bool
    {
        if (self::textLooksLikeTestingAndCommissioning(strtoupper($text))) {
            return true;
        }
        $u = strtoupper($text);
        if (!preg_match('/TESTIN/', $u)) {
            return false;
        }
        if (preg_match('/\bCOMM(?:ERCIAL|ITTEE|UNICATION)\b/', $u)) {
            return false;
        }

        return (bool) preg_match(
            '/COM(?:MISS?IO?N(?:ING)?|M+I+S+S+I+O+N(?:I+NG)?|I{1,2}SSIONING|I{1,2}S+I+O+N+I+NG|MISSION(?:ING)?|MISS?ION|COMI{1,2}SSION(?:ING)?)/',
            $u
        );
    }

    /**
     * Interim / monthly payment application forms — not payment certificates.
     * OCR and subjects often say "Application for payment" rather than "Payment application".
     */
    protected static function textLooksLikePaymentApplication(string $text): bool
    {
        $u = strtoupper($text);
        if (preg_match('/PAYMENT\s*CERTI(?:FICATE|ACATE)|SUBCONTRACTOR\s*PAYMENT\s*CERTI/i', $u)) {
            return false;
        }

        return (bool) preg_match(
            '/PAYMENT\s*APPLICATION'
            . '|APPLICATION\s+FOR\s+(?:THE\s+)?(?:INTERIM\s+)?(?:PAYMENT|PAYMENTS)'
            . '|INTERIM\s+PAYMENT\s+APPLICATION'
            . '|MONTHLY\s+PAYMENT\s+APPLICATION'
            . '|REQUEST\s+FOR\s+(?:INTERIM\s+)?PAYMENT(?:\s+APPLICATION)?'
            . '/i',
            $u
        );
    }

    /**
     * Material submittal forms often reference BOQ schedules in the body or checklist.
     * Prefer the headline / early form title over incidental BOQ mentions later in OCR.
     */
    protected static function textLooksLikeMaterialSubmittal(string $text): bool
    {
        $head = substr(self::normalizeOcrText($text), 0, 4000);

        return (bool) preg_match(
            '/MATERIAL\s*(?:TECHNICAL\s*)?SUBMITTAL(?:\s+FORM)?'
            .'|(?:^|\n)\s*MATERIAL\s*SUBMITTAL\b'
            .'/iu',
            $head
        );
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

        if ($fileCategory !== 'Other') {
            $category = $fileCategory;
            $source = 'filename';
        } elseif ($contentCategory !== 'Other') {
            $category = $contentCategory;
            $source = 'ocr';
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
     * Uses filename first when it encodes a clear folder, then OCR (title → subject → body).
     * When the filename uses a register code (WIR, MIR, SD, …) but OCR (headline **or** full
     * document text for key types such as Testing And Commissioning) indicates another folder, OCR wins.
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
        $ocrHeadlineCategory = $ocr !== '' ? self::guessSubfolderFromOcrHeadingsOnly($ocr) : 'Other';
        $upperName = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
        $hasStrongShopDrawingSignal = (bool) preg_match(
            '/(?:^|[^A-Z0-9])SD(?:[^A-Z0-9]|$)|SHOP\s*DRAWING\s*SUBMITTAL|SHOP\s*DRAWING|\bDWG\b|(?:^|[^A-Z0-9])DS[-_]\d{2,}[-_]\d{2,}|CODE\s+[A-Z0-9]/iu',
            $upperName
        );
        $hasStrongMethodSignal = (bool) preg_match('/(?:^|[^A-Z0-9])(?:MS|MST|MSS|MOS|MTS)(?:[^A-Z0-9]|$)|METHOD\s*STATEMENT/u', $upperName);
        $hasStrongMaterialSignal = (bool) preg_match(
            '/(?:^|[^A-Z0-9])(?:MAT|MB)(?:[^A-Z0-9]|$)|MATERIAL\s*(?:TECHNICAL\s*)?SUBMITTAL|SUBMITTAL\s+TITLE\s*[:\-]\s*MATERIAL|(?:COMMENT|COMMENTS?)\s+ON\s+MATERIAL\s+SUBMITTAL|ENGINEER\s+COMMENT\s+ON\s+MATERIAL\s+SUBMITTAL/i',
            $upperName
        );
        $hasStrongTransmittalSignal = (bool) preg_match(
            '/(?:^|[^A-Z0-9])(?:DTF|DT|TRS|TRM)(?:[^A-Z0-9]|$)|DOCUMENT\s*TRANSMITTAL|\bDS[-_]\d{2,}\b.*\bREV\d/iu',
            $upperName
        );
        $hasStrongMirSignal = (bool) preg_match('/(?:^|[^A-Z0-9])MIR(?:[^A-Z0-9]|$)|MATERIAL\s*INSPECTION\s*REQUEST/u', $upperName);
        $hasStrongWirSignal = (bool) preg_match('/(?:^|[^A-Z0-9])WIR(?:[^A-Z0-9]|$)|WORK\s*INSPECTION/u', $upperName);
        $hasStrongEiSignal = (bool) preg_match('/(?:^|[^A-Z0-9])EI(?:[^A-Z0-9]|$)|ENGINEER\S*\s*INSTRUCTION/i', $upperName);
        $hasStrongRfiSignal = (bool) preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $upperName);
        $hasStrongBoqSignal = (bool) preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $upperName);
        $hasStrongPanReportSignal = (bool) preg_match('/PAN\s*REPORT|PANREPORT/u', $upperName);
        $hasStrongReportSignal = (bool) preg_match('/(?:^|[^A-Z0-9])MPR(?:[^A-Z0-9]|$)|\bKPI\b|PAN\s*REPORT|PANREPORT|MONTHLY[\s\-_A-Z0-9]*REPORT|PROGRESS[\s\-_A-Z0-9]*REPORT|MAINTENANCE[\s\-_A-Z0-9]*REPORT|\bREPORT\b/u', $upperName);
        $hasStrongPaymentCertificateSignal = (bool) preg_match('/PAYMENT\s*CERTI(?:FICATE|ACATE)|(?:^|[^A-Z0-9])PC[#\/\-\s]*\d{1,3}(?:[^A-Z0-9]|$)|SUBCONTRACTOR\s*PAYMENT\s*CERTI/u', $upperName);
        $hasStrongDlcSignal = (bool) preg_match('/DEFECTS?\s+LIABILITY\s+CERTIFICATE|\bDLC\b|REQUEST\s+FOR\s+DEFECTS?\s+LIABILITY/i', $upperName);
        $hasStrongVariationSignal = (bool) preg_match('/\bVARIATION\b|COST\s+VARIATION|VARIATION\s+FOR|DESIGN\s+CHANGE.*?VARIATION/i', $upperName);
        $hasStrongCviSignal = (bool) preg_match('/(?:^|[^A-Z0-9])CVI(?:[^A-Z0-9]|$)/i', $upperName);
        $hasStrongQorSignal = (bool) preg_match('/(?:^|[^A-Z0-9])QOR(?:[^A-Z0-9]|$)/i', $upperName);
        $hasStrongSorSonSignal = (bool) preg_match('/(?:^|[^A-Z0-9])(?:SOR|SON)(?:[^A-Z0-9]|$)/i', $upperName);
        $hasStrongSiteIncidentSignal = (bool) preg_match('/SITE[\s\-]*INCIDENT[\s\-]+REPORT|INCIDENT[\s\-]+(?:REPORT|RERPORT)/i', $upperName);
        $hasStrongTocSignal = (bool) preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $upperName);
        $hasStrongOperationAndMaintenanceSignal = (bool) preg_match(
            '/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/iu',
            $upperName
        );
        $hasStrongPaymentApplicationSignal = self::textLooksLikePaymentApplication($upperName);
        // Engineering letter reference numbering ("...-L003-24", "/L0017/25") is a
        // strong indicator the file is a project letter even when the title alone
        // has no obvious letter keyword.
        $hasStrongLetterRefSignal = (bool) preg_match('/(?:^|[^A-Z0-9])L\d{3,4}[-_\/]\d{2,4}(?:[^A-Z0-9]|$)/u', $upperName);
        $hasStrongFilenameCode = $hasStrongShopDrawingSignal || $hasStrongMethodSignal
            || $hasStrongMaterialSignal || $hasStrongTransmittalSignal
            || $hasStrongMirSignal || $hasStrongWirSignal || $hasStrongEiSignal || $hasStrongRfiSignal || $hasStrongBoqSignal
            || $hasStrongLetterRefSignal || $hasStrongPanReportSignal
            || $hasStrongDlcSignal || $hasStrongVariationSignal
            || $hasStrongCviSignal || $hasStrongQorSignal
            || $hasStrongSorSonSignal || $hasStrongSiteIncidentSignal || $hasStrongTocSignal
            || $hasStrongOperationAndMaintenanceSignal
            || $hasStrongPaymentApplicationSignal;

        // OCR can mistake a submittal title block for a project letter because both
        // carry TO/DATE/REF/SUBJECT labels. If the filename's structured code clearly
        // says otherwise, trust the filename instead of the OCR-as-letter guess.
        if ($contentCategory === 'Incoming Or Outgoing Letter'
            && $fileCategory !== 'Other'
            && $fileCategory !== 'Incoming Or Outgoing Letter'
            && $hasStrongFilenameCode) {
            $contentCategory = 'Other';
        }

        $category = 'Other';
        $source = 'none';
        $confidence = 0.10;

        // Prefer filename-derived category when present; otherwise use OCR (title → subject → body).
        if ($fileCategory !== 'Other') {
            $category = $fileCategory;
            $source = 'filename';
            $confidence = $ocr !== '' ? 0.72 : 0.86;
        } elseif ($contentCategory !== 'Other') {
            $category = $contentCategory;
            $source = 'ocr';
            $confidence = 0.86;
        }

        // Agreement between OCR and filename boosts certainty.
        if ($contentCategory !== 'Other' && $fileCategory !== 'Other' && $contentCategory === $fileCategory) {
            $confidence = 0.95;
        }

        // Filename vs OCR disagree: filename already chosen when non-Other; if OCR-only path, soften when ambiguous.
        if ($fileCategory !== 'Other' && $contentCategory !== 'Other' && $contentCategory !== $fileCategory) {
            $confidence = min($confidence, 0.88);
        }

        // Register filenames often embed WIR/MIR/SD/MAT/MST/DTF codes that do not match the
        // document body. Prefer OCR when (a) headline lines disagree, or (b) headline matches the
        // register code but full-window classification still finds a stronger type (e.g. T&C in body).
        static $filenameCodesOverridableByOcr = [
            'Work Inspection',
            'Material Inspection Request',
            'Shop Drawing',
            'Material Submittal',
            'Method Statement',
            'Document Transmittal',
            'Engineers Instruction',
            'Request For Information',
            'BOQ Bill Of Quantities',
        ];
        static $fullBodyOcrWinsWhenFilenameCodeMatches = [
            'Testing And Commissioning',
            'Site Incident Report',
            'Taking Over Certificate',
            'Material Submittal',
            'Payment Application',
        ];
        if ($ocr !== ''
            && $fileCategory !== 'Other'
            && in_array($fileCategory, $filenameCodesOverridableByOcr, true)) {
            $ocrOverride = 'Other';
            if ($ocrHeadlineCategory !== 'Other'
                && $ocrHeadlineCategory !== 'Incoming Or Outgoing Letter'
                && $ocrHeadlineCategory !== $fileCategory) {
                $ocrOverride = $ocrHeadlineCategory;
            }
            if ($ocrOverride === 'Other'
                && $contentCategory !== 'Other'
                && $contentCategory !== 'Incoming Or Outgoing Letter'
                && $contentCategory !== $fileCategory
                && in_array($contentCategory, $fullBodyOcrWinsWhenFilenameCodeMatches, true)) {
                $ocrOverride = $contentCategory;
            }
            if ($fileCategory === 'Material Submittal'
                && ($hasStrongMaterialSignal || self::textLooksLikeMaterialSubmittal($ocr))
                && $ocrOverride === 'BOQ Bill Of Quantities'
                && !$hasStrongBoqSignal) {
                $ocrOverride = 'Other';
            }
            if ($ocrOverride !== 'Other') {
                $category = $ocrOverride;
                $source = 'ocr';
                $confidence = max($confidence, 0.84);
            }
        }

        if ($ocr !== ''
            && $fileCategory === 'Incoming Or Outgoing Letter'
            && ($ocrHeadlineCategory === 'Taking Over Certificate' || $contentCategory === 'Taking Over Certificate')) {
            $category = 'Taking Over Certificate';
            $source = 'ocr';
            $confidence = max($confidence, 0.84);
        }

        if ($ocr !== ''
            && $fileCategory === 'Incoming Or Outgoing Letter'
            && ($ocrHeadlineCategory === 'Material Submittal' || $contentCategory === 'Material Submittal')) {
            $category = 'Material Submittal';
            $source = 'ocr';
            $confidence = max($confidence, 0.84);
        }

        if ($ocr !== ''
            && $fileCategory === 'Incoming Or Outgoing Letter'
            && ($ocrHeadlineCategory === 'Engineers Instruction' || $contentCategory === 'Engineers Instruction')) {
            $category = 'Engineers Instruction';
            $source = 'ocr';
            $confidence = max($confidence, 0.84);
        }

        if ($ocr !== ''
            && $fileCategory === 'Incoming Or Outgoing Letter'
            && ($ocrHeadlineCategory === 'Payment Application' || $contentCategory === 'Payment Application')) {
            $category = 'Payment Application';
            $source = 'ocr';
            $confidence = max($confidence, 0.84);
        }

        if ($ocr !== ''
            && $fileCategory === 'Incoming Or Outgoing Letter'
            && ($ocrHeadlineCategory === 'Request For Information' || $contentCategory === 'Request For Information')) {
            $category = 'Request For Information';
            $source = 'ocr';
            $confidence = max($confidence, 0.84);
        }

        if ($ocr !== ''
            && $fileCategory === 'Incoming Or Outgoing Letter'
            && ($ocrHeadlineCategory === 'BOQ Bill Of Quantities' || $contentCategory === 'BOQ Bill Of Quantities')) {
            $category = 'BOQ Bill Of Quantities';
            $source = 'ocr';
            $confidence = max($confidence, 0.84);
        }

        // Structured file names with an explicit type code (e.g. ...-SD-..., -WIR-,
        // -MIR-, -MST-, -MAT-, -DTF-) are usually reliable even when OCR is weak
        // or unavailable. Treat any of those as strong evidence for the matching
        // category so they cross the auto-classification confidence threshold.
        $strongSignalForCategory = [
            'Shop Drawing' => $hasStrongShopDrawingSignal,
            'Work Inspection' => $hasStrongWirSignal,
            'Material Inspection Request' => $hasStrongMirSignal,
            'Engineers Instruction' => $hasStrongEiSignal,
            'Request For Information' => $hasStrongRfiSignal,
            'BOQ Bill Of Quantities' => $hasStrongBoqSignal,
            'Method Statement' => $hasStrongMethodSignal,
            'Material Submittal' => $hasStrongMaterialSignal,
            'Monthly Report' => $hasStrongReportSignal,
            'KPI Report' => $hasStrongReportSignal,
            'Payment Certificate' => $hasStrongPaymentCertificateSignal,
            'Defect Liability Certificate' => $hasStrongDlcSignal,
            'Variation' => $hasStrongVariationSignal,
            'Confirmation Of Verbal Instruction' => $hasStrongCviSignal,
            'Quality Observation Report' => $hasStrongQorSignal,
            'Site Observation Report' => $hasStrongSorSonSignal,
            'Site Incident Report' => $hasStrongSiteIncidentSignal,
            'Taking Over Certificate' => $hasStrongTocSignal,
            'Operation And Maintenance Manual' => $hasStrongOperationAndMaintenanceSignal,
            'Payment Application' => $hasStrongPaymentApplicationSignal,
            'Document Transmittal' => $hasStrongTransmittalSignal,
            'Incoming Or Outgoing Letter' => $hasStrongLetterRefSignal,
        ];
        if ($source === 'filename'
            && isset($strongSignalForCategory[$category])
            && $strongSignalForCategory[$category]) {
            $confidence = max($confidence, 0.80);
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
        // Avoid treating "Submission of … certificates" HR/memo filenames as letters (SUBMISSION alone is too broad).
        $hasLetterToken = (bool) preg_match('/(?:^|[^A-Z0-9])(?:LTR|LOR|LETTER|NOTICE|CORRESP(?:ONDENCE)?|COMMENTS?)(?:[^A-Z0-9]|$)/i', $upper);
        // Engineering letter reference numbering ("...-L003-24", "/L0017/25")
        // is also a strong indicator that a file is a project letter.
        $hasLetterRefNumber = (bool) preg_match('/(?:^|[^A-Z0-9])L\d{3,4}[-_\/]\d{2,4}(?:[^A-Z0-9]|$)/i', $upper);
        $hasLetterToken = $hasLetterToken || $hasLetterRefNumber;
        $hasTransmittalToken = (bool) preg_match('/\bDTF\b|\bDT\b|DOC\.?\s*TRANS|DOCUMENT\s*TRANSMITTAL|TRANSMITTAL\s*NOTE/i', $upper);
        // Register segment ...-L0002-24 sets letter heuristics; explicit material-submittal wording in the title must still win.
        $hasMaterialSubmittalKeyword = (bool) preg_match(
            '/MATERIAL\s*(?:TECHNICAL\s*)?SUBMITTAL|SUBMITTAL\s+TITLE\s*[:\-]\s*MATERIAL|(?:COMMENT|COMMENTS?)\s+ON\s+MATERIAL\s+SUBMITTAL|ENGINEER\s+COMMENT\s+ON\s+MATERIAL\s+SUBMITTAL/i',
            $upper
        );

        // Keep As-Built docs out of Method Statement even if code contains "-MS-".
        if (preg_match('/\bAS[\s\-]*BUILT\b|\bASBUILT\b/i', $upper) && !$hasLetterToken) {
            return 'As Built Drawing Submittal';
        }

        if (preg_match('/INTERNAL\s*MEMO|MEMORANDUM|\b[A-Z]{2,}(?:-[A-Z]{2,})*-MEM-\d+/u', $upper)) {
            return 'Internal Memo';
        }
        if (preg_match('/\bIMO\d{3,5}\/SM\/[A-Z]{2}\/\d{2,4}\b/u', $upper)) {
            return 'Internal Memo';
        }
        if (preg_match('/\bTENDER\b|\bTENDER\s+DOCUMENT/i', $upper)) {
            return 'Enquireis';
        }

        // Defect liability / DLC must win before letter-ref heuristics (filenames often contain ...-L0374-24...).
        if (preg_match('/DEFECTS?\s+LIABILITY\s+CERTIFICATE|\bDLC\b|REQUEST\s+FOR\s+DEFECTS?\s+LIABILITY/i', $upper)) {
            return 'Defect Liability Certificate';
        }

        if (preg_match('/\bNO\s+OBJECTION\b|(?:^|[^A-Z0-9])NOC(?:[^A-Z0-9]|$)|WATER\s+NOC|ELECTRIC(?:ITY)?\s+NOC/i', $upper)) {
            return 'Permit and NOC';
        }
        if (preg_match('/\bPERMIT\b|BUILDING\s+PERMIT|WORK\s+PERMIT|CONSTRUCTION\s+PERMIT/i', $upper)) {
            return 'Permit and NOC';
        }

        // Variation / cost variation: filename often still contains ...-L0039-23... letter-style ref.
        if (preg_match('/\bVARIATION\b|COST\s+VARIATION|VARIATION\s+FOR|VARIATION\s+REQUEST|DESIGN\s+CHANGE.*?VARIATION/i', $upper)) {
            return 'Variation';
        }
        // CVI = Confirmation of Verbal Instruction (common ref segment ...-CVI-002...)
        if (preg_match('/(?:^|[^A-Z0-9])CVI(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Confirmation Of Verbal Instruction';
        }
        // QOR = Quality Observation Report (e.g. QOR-PRO-BK-0001_Closed.pdf)
        if (preg_match('/(?:^|[^A-Z0-9])QOR(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Quality Observation Report';
        }
        // SOR / SON = Site Observation Report (common register refs; "Closed" in name is workflow status only)
        if (preg_match('/(?:^|[^A-Z0-9])SOR(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Site Observation Report';
        }
        if (preg_match('/(?:^|[^A-Z0-9])SON(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Site Observation Report';
        }
        // Incident reports: ref segment ...-L0102-24- is a letter pattern but title is "Incident Report" / "Incident Rerport".
        if (preg_match('/SITE[\s\-]*INCIDENT[\s\-]+REPORT|INCIDENT[\s\-]+(?:REPORT|RERPORT)/i', $upper)) {
            return 'Site Incident Report';
        }
        if (preg_match('/TAKING\s*OVER\s*CERTIFICATE|\bTOC\b/i', $upper)) {
            return 'Taking Over Certificate';
        }
        if (self::textLooksLikeTestingAndCommissioning($upper)) {
            return 'Testing And Commissioning';
        }

        // Register "OMM" / "O&M" (and spelled-out title) must win over material codes like "MAS" in the same path.
        if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Operation And Maintenance Manual';
        }

        if (self::textLooksLikePaymentApplication($upper)) {
            return 'Payment Application';
        }
        if (preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Request For Information';
        }
        if (preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $upper)) {
            return 'BOQ Bill Of Quantities';
        }

        if ($hasMaterialSubmittalKeyword) {
            return 'Material Submittal';
        }

        // Prioritize clear letter/correspondence documents before generic short-code matches
        // like trailing "/MB" fragments in reference numbers.
        if ($hasLetterToken && !$hasTransmittalToken) {
            return 'Incoming Or Outgoing Letter';
        }

        $codeMatches = [];
        preg_match_all('/(?:^|[^A-Z0-9])(DTF|DT|TRS|TRM|MIR|WIR|EI|RFI|BOQ|MTS|MST|MSS|MOS|MT|SD|DS|DWG|ASB|ABS|MAT|MSA|MAS|MB|PQ|PREQ|PREQUL|MIRR)(?:[^A-Z0-9]|$)/i', $upper, $codeMatches);
        $codes = array_unique(array_map('strtoupper', $codeMatches[1] ?? []));
        $hasPrequalificationKeyword = (bool) preg_match('/PRE[\s\-]*QUALIF(?:ICATION|ICATIONS)?|\bPREQUAL\b|\bPREQ\b/i', $upper);
        $hasMethodKeyword = (bool) preg_match('/METHOD\s*STATEMENT|METHOD\s+OF\s+STATEMENT|METHOD\s*ST(?:\.|ATEMENT)?|STATEMENT\s+SUBMITTAL|\bMTS\b|\bMST\b|\bMSS\b|\bMOS\b/i', $upper);
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

        // Explicit business-document labels should win over ambiguous short codes
        // like "-MS-" that can also appear in non-submittal naming conventions.
        if (preg_match('/\bKPI\b|\bKEY\s*PERFORMANCE\s*INDICATOR\b/i', $upper)) {
            return 'KPI Report';
        }
        if (preg_match('/(?:^|[^A-Z0-9])MPR(?:[^A-Z0-9]|$)|PAN\s*REPORT|PANREPORT|MONTHLY[\s\-_A-Z0-9]*REPORT|PROGRESS[\s\-_A-Z0-9]*REPORT|MAINTENANCE[\s\-_A-Z0-9]*REPORT/i', $upper)) {
            return 'Monthly Report';
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
        // Drawing sheet index only when it looks like a sheet trail (e.g. ...-DS-058-01-Code A.pdf),
        // not bare ...-DS-009 Rev... (often document / transmittal register).
        if (in_array('DS', $codes, true)) {
            if (preg_match('/SHOP|DRAWING|\bDWG\b|CODE\s+[A-Z0-9]|DS[-_]\d{2,}[-_]\d{2,}/i', $upper)) {
                return 'Shop Drawing';
            }
            if (preg_match('/\bREV\d/i', $upper)) {
                return 'Document Transmittal';
            }
        }
        if (in_array('MIR', $codes, true) || in_array('MIRR', $codes, true)) {
            return 'Material Inspection Request';
        }
        if (in_array('EI', $codes, true)) {
            return 'Engineers Instruction';
        }
        if (in_array('RFI', $codes, true)) {
            return 'Request For Information';
        }
        if (in_array('BOQ', $codes, true)) {
            return 'BOQ Bill Of Quantities';
        }
        if (in_array('WIR', $codes, true)) {
            return 'Work Inspection';
        }
        if (in_array('ASB', $codes, true) || in_array('ABS', $codes, true)) {
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
        if (in_array('MAT', $codes, true) || in_array('MAS', $codes, true)) {
            return 'Material Submittal';
        }
        if (in_array('MSA', $codes, true)) {
            return 'Material Sample';
        }
        if (in_array('MB', $codes, true)) {
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
        // "WORK INSPECTION REQUEST" contains INSPECTION+REQUEST — classify work before generic MIR phrase.
        if (preg_match('/WORK\s*INSPECTION/i', $upper)) {
            return 'Work Inspection';
        }
        if (preg_match('/MATERIAL\s*INSPECTION\s*REQUEST|\bMIR\b/i', $upper)
            || (preg_match('/INSPECTION\s*REQUEST/i', $upper) && !preg_match('/WORK\s*INSPECTION/i', $upper))) {
            return 'Material Inspection Request';
        }
        if (preg_match('/MATERIAL\s*SUBMITTAL|\bMAT(?:ERIAL)?\s*SUB(?:MITTAL)?\b/i', $upper)) {
            return 'Material Submittal';
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
        if (!$hasTransmittalToken && $hasLetterToken) {
            return 'Incoming Or Outgoing Letter';
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
        // Fallback: when filename explicitly says "report" but no specialized report
        // type was matched above, place under Monthly Report instead of Other.
        if (preg_match('/\bREPORT\b/i', $upper)) {
            return 'Monthly Report';
        }
        if (preg_match('/PAYMENT\s*CERTI(?:FICATE|ACATE)|SUBCONTRACTOR\s*PAYMENT\s*CERTI|(?:^|[^A-Z0-9])PC[#\/\-\s]*\d{1,3}(?:[^A-Z0-9]|$)/i', $upper)) {
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
        if (preg_match('/\bNO\s+OBJECTION\b|(?:^|[^A-Z0-9])NOC(?:[^A-Z0-9]|$)|WATER\s+NOC|ELECTRIC(?:ITY)?\s+NOC/i', $upper)) {
            return 'Permit and NOC';
        }
        if (preg_match('/\bPERMIT\b|BUILDING\s+PERMIT|WORK\s+PERMIT|CONSTRUCTION\s+PERMIT/i', $upper)) {
            return 'Permit and NOC';
        }
        if (preg_match('/DEFECTS?\s+LIABILITY\s+CERTIFICATE|\bDLC\b|REQUEST\s+FOR\s+DEFECTS?\s+LIABILITY/i', $upper)) {
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
        if (preg_match('/OPERATION\s*AND\s*MAINTENANCE|\bO&M\b|(?:^|[^A-Z0-9])OMM(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Operation And Maintenance Manual';
        }
        if (self::textLooksLikePaymentApplication($upper)) {
            return 'Payment Application';
        }
        if (preg_match('/(?:^|[^A-Z0-9])QOR(?:[^A-Z0-9]|$)|QUALITY\s*OBSERVATION\s*REPORT/i', $upper)) {
            return 'Quality Observation Report';
        }
        if (preg_match('/REQUEST\s*FOR\s*INFORMATION|(?:^|[^A-Z0-9])RFI(?:[^A-Z0-9]|$)/i', $upper)) {
            return 'Request For Information';
        }
        if (preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $upper)) {
            return 'BOQ Bill Of Quantities';
        }
        if (preg_match('/(?:^|[^A-Z0-9])(?:SOR|SON)(?:[^A-Z0-9]|$)|SITE\s*OBSERVATION\s*REPORT/i', $upper)) {
            return 'Site Observation Report';
        }
        if (preg_match('/SITE[\s\-]*INCIDENT[\s\-]+REPORT|INCIDENT[\s\-]+(?:REPORT|RERPORT)/i', $upper)) {
            return 'Site Incident Report';
        }
        if (self::textLooksLikeTestingAndCommissioning($upper)) {
            return 'Testing And Commissioning';
        }
        if (preg_match('/(?:^|[^A-Z0-9])CVI(?:[^A-Z0-9]|$)|CONFIRMATION\s+OF\s+VERBAL|VERBAL\s*INSTRUCTION/i', $upper)) {
            return 'Confirmation Of Verbal Instruction';
        }
        if (preg_match('/VARIATION/i', $upper)) {
            return 'Variation';
        }
        if (preg_match('/WARRANTY/i', $upper)) {
            return 'Warranty By Us';
        }
        if (preg_match('/PROJECT\s+TECHNICAL\s+DOCUMENTS?|TECHNICAL\s+DOCUMENTATION\s+FOR\s+PROJECT|\bPTD\b/i', $upper)) {
            return 'Project Technical Documents';
        }
        if (preg_match('/DESIGN\s*CALCULATION/i', $upper)) {
            return 'Design Calculation';
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
        // Require explicit "PURCHASE ORDER" or PO followed by a number/NO so
        // letterhead addresses like "P.O. Box 12345" do not trigger this.
        if (preg_match('/PURCHASE\s*ORDER/i', $upper)
            || preg_match('/\bPO\b(?!\s*BOX)\s*(?:NO\.?|NUMBER|#|\d)/i', $upper)) {
            return 'Purchase Order';
        }
        if (preg_match('/PURCHASE\s*REQUEST/i', $upper)
            || preg_match('/\bPR\b(?!\s*OFFICE)\s*(?:NO\.?|NUMBER|#|\d)/i', $upper)) {
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
        if (preg_match('/(?:^|[^A-Z0-9])BOQ(?:[^A-Z0-9]|$)|\bBILL\s+OF\s+QUANTITIES\b|\bBILL\s+OF\s+QUANTITY\b/i', $upper)) {
            return 'BOQ Bill Of Quantities';
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
    /**
     * Resolve the best "sub folder" label to display for a row, preferring
     * a category derived from the filename (and OCR text when available)
     * so the UI stays correct even when the stored document_type is stale,
     * empty, or wrong.
     *
     * Rules, in order:
     *  1. If OCR text is present and classifyForAutomation (or payment-body heuristics)
     *     yield a confident category (>= 0.70), or a clear payment application from OCR,
     *     use that.
     *  2. Otherwise, parse the filename; if that yields only the generic letter bucket but the
     *     stored document_type is a more specific subfolder (e.g. after reclassification),
     *     prefer the stored value so the UI matches the database until OCR is available.
     *  3. Otherwise, use the filename parse when it is non-Other.
     *  4. Otherwise, fall back to the stored document_type.
     */
    protected static function resolveSubLabel(?string $documentType, ?string $fileName, ?string $ocrText = null): ?string
    {
        $type = $documentType !== null && trim($documentType) !== '' ? trim($documentType) : null;

        if ($fileName !== null && $fileName !== '') {
            $ocr = is_string($ocrText) && $ocrText !== '' ? $ocrText : null;
            if ($ocr !== null) {
                $auto = self::classifyForAutomation($fileName, $ocr);
                $autoCat = (string) ($auto['document_category'] ?? '');
                $autoConf = (float) ($auto['confidence'] ?? 0);
                if ($autoCat === 'Incoming Or Outgoing Letter' && self::textLooksLikePaymentApplication($ocr)) {
                    return 'Payment Application';
                }
                if ($autoCat !== '' && $autoCat !== 'Other' && $autoConf >= 0.70) {
                    return $autoCat;
                }
                if (self::textLooksLikePaymentApplication($ocr)) {
                    return 'Payment Application';
                }
            }

            $parsed = self::parse($fileName);
            $cat = $parsed['document_category'] ?? null;
            if ($cat !== null && $cat !== '' && $cat !== 'Other') {
                if ($cat === 'Incoming Or Outgoing Letter'
                    && $type !== null
                    && $type !== ''
                    && $type !== 'Other'
                    && $type !== 'Incoming Or Outgoing Letter') {
                    return $type;
                }

                return $cat;
            }
        }

        return $type;
    }

    public static function folderDisplayLabel(?string $documentType, ?string $fileName = null, ?string $ocrText = null): string
    {
        $type = self::resolveSubLabel($documentType, $fileName, $ocrText);
        if ($type === null) {
            return '—';
        }
        $main = self::mainFolderForDocumentType($type);

        return $main !== null ? $main.' / '.$type : $type;
    }

    public static function folderSubLabel(?string $documentType, ?string $fileName = null, ?string $ocrText = null): string
    {
        $type = self::resolveSubLabel($documentType, $fileName, $ocrText);

        return $type !== null && $type !== '' ? $type : '—';
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
                'Permit and NOC',
            ],
            'Project Correspondence' => [
                'BOQ Bill Of Quantities',
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
                'Design Calculation',
                'Confirmation Of Verbal Instruction',
                'Project Technical Documents',
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
