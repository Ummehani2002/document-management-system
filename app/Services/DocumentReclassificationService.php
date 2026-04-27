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

        try {
            Storage::disk($disk)->makeDirectory($folderPath);
            Storage::disk($disk)->move($oldPath, $newPath);
        } catch (\Throwable $e) {
            Log::warning('Document reclassification move failed', [
                'document_id' => $document->id,
                'from' => $oldPath,
                'to' => $newPath,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $document->update([
            'document_type' => $newCategory,
            'file_path' => $newPath,
            'file_name' => basename($newPath),
        ]);
    }
}
