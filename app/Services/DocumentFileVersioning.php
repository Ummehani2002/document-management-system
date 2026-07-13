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
        $normalized = preg_replace('/\s*-\s*$/u', '', $normalized) ?? $normalized;

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

    /**
     * Next filename when saving an edited copy (V1, V2, …). Keeps prior versions.
     */
    public static function buildNextEditVersionFilename(string $currentFileName, int $projectId, string $category): string
    {
        $extension = pathinfo($currentFileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($currentFileName, PATHINFO_FILENAME);
        $rootBase = self::stripEditVersionSuffix($baseName);
        $targetKey = self::versionKey($rootBase);

        $maxVersion = 0;
        $existingNames = Document::query()
            ->where('project_id', $projectId)
            ->where('document_type', $category)
            ->pluck('file_name');

        foreach ($existingNames as $existingName) {
            $existingBase = pathinfo((string) $existingName, PATHINFO_FILENAME);
            $existingRoot = self::stripEditVersionSuffix($existingBase);
            if (self::versionKey($existingRoot) !== $targetKey) {
                continue;
            }
            if (preg_match('/\s+V(\d+)$/i', $existingBase, $matches)) {
                $maxVersion = max($maxVersion, (int) $matches[1]);
            } else {
                $maxVersion = max($maxVersion, 0);
            }
        }

        $nextVersion = $maxVersion + 1;
        $newBase = $rootBase.' V'.$nextVersion;

        return $extension !== '' ? ($newBase.'.'.$extension) : $newBase;
    }

    public static function stripEditVersionSuffix(string $baseName): string
    {
        $stripped = preg_replace('/\s*-\s*Version\s+\d+$/iu', '', $baseName) ?? $baseName;
        $stripped = preg_replace('/\s+V\d+$/i', '', $stripped) ?? $stripped;

        return trim($stripped);
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

    public static function logicalFamilyKey(string $fileName): string
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);

        return self::versionKey(self::stripEditVersionSuffix($baseName));
    }

    /**
     * @return array{0:int,1:int}
     */
    public static function versionRank(string $fileName): array
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $revision = self::extractVersionNumber($baseName);
        $editVersion = 0;

        if (preg_match('/\s+V(\d+)$/i', $baseName, $matches)) {
            $editVersion = (int) $matches[1];
        }

        return [$revision, $editVersion];
    }

    public static function isNewerFilename(string $left, string $right): bool
    {
        return self::compareFilenames($left, $right) > 0;
    }

    public static function compareFilenames(string $left, string $right): int
    {
        [$leftRevision, $leftEdit] = self::versionRank($left);
        [$rightRevision, $rightEdit] = self::versionRank($right);

        if ($leftRevision !== $rightRevision) {
            return $leftRevision <=> $rightRevision;
        }

        if ($leftEdit !== $rightEdit) {
            return $leftEdit <=> $rightEdit;
        }

        return strnatcasecmp($left, $right);
    }

    /**
     * @param  iterable<int, Document|object{id:int, file_name:string, project_id:int}>  $documents
     * @return list<int>
     */
    public static function pickLatestDocumentIds(iterable $documents): array
    {
        $latestByFamily = [];

        foreach ($documents as $document) {
            $familyKey = ((int) $document->project_id).'|'.self::logicalFamilyKey((string) $document->file_name);
            if (
                ! isset($latestByFamily[$familyKey])
                || self::isNewerFilename((string) $document->file_name, (string) $latestByFamily[$familyKey]->file_name)
            ) {
                $latestByFamily[$familyKey] = $document;
            }
        }

        return array_values(array_map(static fn ($document) => (int) $document->id, $latestByFamily));
    }

    /**
     * @param  iterable<int, int>  $projectIds
     * @return array<string, int>
     */
    public static function familyCountsForProjects(iterable $projectIds): array
    {
        $projectIdList = array_values(array_unique(array_map('intval', [...$projectIds])));
        if ($projectIdList === []) {
            return [];
        }

        $counts = [];
        $documents = Document::query()
            ->whereIn('project_id', $projectIdList)
            ->get(['id', 'file_name', 'project_id']);

        foreach ($documents as $document) {
            $familyKey = ((int) $document->project_id).'|'.self::logicalFamilyKey((string) $document->file_name);
            $counts[$familyKey] = ($counts[$familyKey] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Document>
     */
    public static function versionFamilyDocuments(Document $document): \Illuminate\Support\Collection
    {
        $familyKey = self::logicalFamilyKey((string) $document->file_name);

        return Document::query()
            ->where('project_id', $document->project_id)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(static fn (Document $row) => self::logicalFamilyKey((string) $row->file_name) === $familyKey)
            ->values();
    }
}
