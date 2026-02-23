<?php

namespace App\Services;

use App\Models\Project;

class DocumentFilenameParser
{
    /** Folder categories we use (must match DocumentController::documentCategories) */
    protected static array $categories = [
        'Document Transmittal',
        'Form',
        'Method Submittal',
        'Drawing',
        'Specification',
        'Report',
        'Correspondence',
        'Other',
    ];

    /**
     * Parse a PDF filename and suggest project number and document category (folder).
     * E.g. "PSE20231011-PRS-PAR-DTF-00056 R.00 - Monthly Progress Report No.5 as of 20 Nov 2023.pdf"
     * → project_number: PSE20231011, category: Report
     */
    public static function parse(string $filename): array
    {
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        $upper = strtoupper($filename);

        $projectNumber = self::extractProjectNumber($filename);
        $category = self::guessCategoryFromTitle($filename, $upper);

        return [
            'project_number' => $projectNumber,
            'document_category' => $category,
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
     * Guess document category from filename/title and code patterns (e.g. DTF, MTS).
     */
    protected static function guessCategoryFromTitle(string $filename, string $upper): string
    {
        // Title keywords first (e.g. "Monthly Progress Report" → Report)
        if (preg_match('/PROGRESS\s*REPORT|MONTHLY\s*REPORT|REPORT\s*NO\./i', $filename)) {
            return 'Report';
        }
        if (preg_match('/\bREPORT\b/i', $filename)) {
            return 'Report';
        }

        // Code patterns in filename (e.g. ...-DTF-... = Document Transmittal)
        if (preg_match('/\bDTF\b/', $upper) || preg_match('/DOCUMENT\s*TRANSMITTAL|TRANSMITTAL\s*FORM/i', $filename)) {
            return 'Document Transmittal';
        }
        if (preg_match('/\bMTS\b/', $upper) || preg_match('/METHOD\s*SUBMITTAL|METHOD\s*STATEMENT/i', $filename)) {
            return 'Method Submittal';
        }
        if (preg_match('/\bDRW\b|\bDWG\b/', $upper) || preg_match('/\bDRAWING\b/i', $filename)) {
            return 'Drawing';
        }
        if (preg_match('/\bSPEC\b/i', $filename) || preg_match('/SPECIFICATION/i', $filename)) {
            return 'Specification';
        }
        if (preg_match('/\bFORM\b/i', $filename) && !preg_match('/TRANSMITTAL/i', $filename)) {
            return 'Form';
        }
        if (preg_match('/CORRESPONDENCE|LETTER|EMAIL/i', $filename)) {
            return 'Correspondence';
        }

        // More title keywords
        if (preg_match('/SPECIFICATION|SPEC\s/i', $filename)) {
            return 'Specification';
        }
        if (preg_match('/DRAWING|DRG\b/i', $filename)) {
            return 'Drawing';
        }
        if (preg_match('/METHOD\s*SUBMITTAL|METHOD\s*STATEMENT/i', $filename)) {
            return 'Method Submittal';
        }
        if (preg_match('/TRANSMITTAL|DTF/i', $filename)) {
            return 'Document Transmittal';
        }
        if (preg_match('/FORM\b/i', $filename)) {
            return 'Form';
        }
        if (preg_match('/CORRESPONDENCE|LETTER/i', $filename)) {
            return 'Correspondence';
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
}
