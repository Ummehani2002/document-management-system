<?php

namespace App\Services;

use App\Models\Document;

/**
 * Shared versioned naming for uploads and folder moves (same logic as upload flow).
 */
class DocumentFileVersioning
{
    public static function buildVersionedFilename(string $originalName, int $projectId, string $category): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        $targetKey = self::versionKey($nameWithoutExt);

        $existingNames = Document::query()
            ->where('project_id', $projectId)
            ->where('document_type', $category)
            ->pluck('file_name');

        $maxVersion = -1;
        foreach ($existingNames as $existingName) {
            $existingBase = pathinfo((string) $existingName, PATHINFO_FILENAME);
            if (self::versionKey($existingBase) !== $targetKey) {
                continue;
            }
            $maxVersion = max($maxVersion, self::extractVersionNumber($existingBase));
        }

        if ($maxVersion < 0) {
            return $originalName;
        }

        $nextVersion = $maxVersion + 1;
        $nextBase = self::injectVersionNumber($nameWithoutExt, $nextVersion);

        return $extension !== '' ? ($nextBase . '.' . $extension) : $nextBase;
    }

    public static function versionKey(string $baseName): string
    {
        $normalized = strtoupper($baseName);
        $normalized = preg_replace('/\bR\s*NO\.?\s*[-.:]?\s*\d+\b/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bR(?:EV(?:ISION)?)?\s*[-.:]?\s*\d+\b/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bV(?:ERSION)?\s*[-.:]?\s*\d+\b/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public static function extractVersionNumber(string $baseName): int
    {
        if (preg_match('/\bR\s*NO\.?\s*[-.:]?\s*(\d+)\b/ui', $baseName, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bR(?:EV(?:ISION)?)?\s*[-.:]?\s*(\d+)\b/ui', $baseName, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bV(?:ERSION)?\s*[-.:]?\s*(\d+)\b/ui', $baseName, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    public static function injectVersionNumber(string $baseName, int $version): string
    {
        if (preg_match('/\bR\s*NO\.?\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bR\s*NO\.?\s*[-.:]?\s*\d+\b/ui', 'R NO ' . $version, $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bR\.\d+\b/ui', $baseName)) {
            return preg_replace('/\bR\.\d+\b/ui', 'R.' . str_pad((string) $version, 2, '0', STR_PAD_LEFT), $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bREV(?:ISION)?\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bREV(?:ISION)?\s*[-.:]?\s*\d+\b/ui', 'REV-' . str_pad((string) $version, 2, '0', STR_PAD_LEFT), $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bR\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bR\s*[-.:]?\s*\d+\b/ui', 'R.' . str_pad((string) $version, 2, '0', STR_PAD_LEFT), $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bV(?:ERSION)?\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bV(?:ERSION)?\s*[-.:]?\s*\d+\b/ui', 'Version ' . $version, $baseName, 1) ?? $baseName;
        }

        return $baseName . ' - Version ' . $version;
    }
}
