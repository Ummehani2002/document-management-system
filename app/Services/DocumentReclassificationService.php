<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * After first-page OCR, re-assign document_type and move storage when header/reference text implies a better folder.
 */
class DocumentReclassificationService
{
    public function refineAfterOcr(Document $document): void
    {
        $document->refresh();
        $ocr = trim((string) $document->ocr_text);
        $minConfidence = (float) env('DOC_AUTO_CLASSIFY_MIN_CONFIDENCE', 0.70);

        // Automated mixed-format classification with confidence gating.
        $placement = DocumentFilenameParser::classifyForAutomation($document->file_name, $ocr !== '' ? $ocr : null);
        $newCategory = $placement['document_category'] ?? 'Other';
        $confidence = (float) ($placement['confidence'] ?? 0.0);
        if ($newCategory === 'Other') {
            return;
        }
        if ($confidence < $minConfidence) {
            Log::info('Document reclassification skipped: low confidence', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'suggested_category' => $newCategory,
                'confidence' => $confidence,
                'threshold' => $minConfidence,
                'source' => $placement['category_source'] ?? 'unknown',
            ]);

            return;
        }

        if ($newCategory === $document->document_type) {
            return;
        }

        $document->loadMissing(['entity', 'project']);
        $entity = $document->entity;
        $project = $document->project;
        if (!$entity || !$project) {
            return;
        }

        $disk = config('filesystems.default');
        $oldPath = $document->file_path;
        if (!$oldPath || !Storage::disk($disk)->exists($oldPath)) {
            return;
        }

        $folderPath = 'documents/'
            . Str::slug($entity->name) . '/'
            . Str::slug($project->project_number) . '/'
            . Str::slug($newCategory);

        $storedFileName = DocumentFileVersioning::buildVersionedFilename($document->file_name, $project->id, $newCategory);
        $newPath = $folderPath . '/' . $storedFileName;

        if ($oldPath === $newPath) {
            $document->update(['document_type' => $newCategory]);

            return;
        }

        // Avoid overwriting a real file that already lives at the planned path
        // (buildVersionedFilename only consults DB rows, not actual storage objects).
        $newPath = $this->ensureUniqueStoragePath($disk, $newPath);
        $storedFileName = basename($newPath);

        try {
            Storage::disk($disk)->makeDirectory($folderPath);
            $moved = Storage::disk($disk)->move($oldPath, $newPath);
        } catch (\Throwable $e) {
            Log::warning('Document reclassification move failed (exception)', [
                'document_id' => $document->id,
                'from' => $oldPath,
                'to' => $newPath,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($moved !== true || !Storage::disk($disk)->exists($newPath)) {
            Log::warning('Document reclassification move failed (silent)', [
                'document_id' => $document->id,
                'from' => $oldPath,
                'to' => $newPath,
                'move_return' => $moved,
                'old_still_exists' => Storage::disk($disk)->exists($oldPath),
                'new_exists' => Storage::disk($disk)->exists($newPath),
            ]);

            return;
        }

        $document->update([
            'document_type' => $newCategory,
            'file_path' => $newPath,
            'file_name' => $storedFileName,
        ]);
    }

    /**
     * Find a storage path that does not already exist by appending " (n)"
     * before the extension if necessary. Limits attempts to keep it bounded.
     */
    protected function ensureUniqueStoragePath(string $disk, string $path): string
    {
        if (!Storage::disk($disk)->exists($path)) {
            return $path;
        }

        $directory = ltrim((string) pathinfo($path, PATHINFO_DIRNAME), '.');
        $extension = (string) pathinfo($path, PATHINFO_EXTENSION);
        $base = (string) pathinfo($path, PATHINFO_FILENAME);

        for ($i = 1; $i <= 50; $i++) {
            $candidate = $base . ' (' . $i . ')' . ($extension !== '' ? ('.' . $extension) : '');
            $candidatePath = $directory !== '' ? ($directory . '/' . $candidate) : $candidate;
            if (!Storage::disk($disk)->exists($candidatePath)) {
                return $candidatePath;
            }
        }

        return $path;
    }
}
