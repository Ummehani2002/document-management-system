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

        Log::info('Document reclassification started', [
            'document_id' => $document->id,
            'file_name' => $document->file_name,
            'current_type' => $document->document_type,
            'has_ocr_text' => $ocr !== '',
        ]);

        // Automated mixed-format classification with confidence gating.
        $placement = DocumentFilenameParser::classifyForAutomation($document->file_name, $ocr !== '' ? $ocr : null);
        $newCategory = $placement['document_category'] ?? 'Other';
        $confidence = (float) ($placement['confidence'] ?? 0.0);

        if ($newCategory === 'Other') {
            Log::info('Document reclassification skipped: classifier returned Other', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'confidence' => $confidence,
                'source' => $placement['category_source'] ?? 'unknown',
            ]);

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
            Log::info('Document reclassification skipped: already in target category', [
                'document_id' => $document->id,
                'category' => $newCategory,
            ]);

            return;
        }

        $document->loadMissing(['entity', 'project']);
        $entity = $document->entity;
        $project = $document->project;
        if (!$entity || !$project) {
            Log::warning('Document reclassification skipped: missing entity/project', [
                'document_id' => $document->id,
                'entity_id' => $document->entity_id,
                'project_id' => $document->project_id,
            ]);

            return;
        }

        $oldPath = (string) $document->file_path;
        if ($oldPath === '') {
            Log::warning('Document reclassification skipped: empty file_path', [
                'document_id' => $document->id,
            ]);

            return;
        }

        // Use the same resolver the UI uses so we tolerate eventual consistency / disk
        // mismatches between the configured default disk and where the file actually lives.
        $location = DocumentLocationResolver::resolve($oldPath);
        if ($location === null || ($location['source'] ?? '') !== 'disk') {
            Log::warning('Document reclassification skipped: source file not found on a managed disk', [
                'document_id' => $document->id,
                'old_path' => $oldPath,
                'resolved' => $location,
            ]);

            return;
        }

        $disk = $location['disk'];
        $oldPath = $location['path'];

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

        $moveSucceeded = $this->moveOnDisk($disk, $oldPath, $newPath, $folderPath, $document->id);
        if (!$moveSucceeded) {
            return;
        }

        $document->update([
            'document_type' => $newCategory,
            'file_path' => $newPath,
            'file_name' => $storedFileName,
        ]);

        Log::info('Document reclassification succeeded', [
            'document_id' => $document->id,
            'category' => $newCategory,
            'new_path' => $newPath,
        ]);
    }

    /**
     * Move a file. Prefers the underlying Flysystem driver so that real errors
     * (e.g. S3 AccessDenied on CopyObject) bubble up with their message instead
     * of being swallowed by the Storage wrapper which only returns false.
     * Falls back to copy + delete when move is not supported by the driver.
     */
    protected function moveOnDisk(string $disk, string $from, string $to, string $folderPath, int $documentId): bool
    {
        try {
            Storage::disk($disk)->makeDirectory($folderPath);
        } catch (\Throwable $e) {
            Log::debug('Document reclassification makeDirectory ignored', [
                'document_id' => $documentId,
                'disk' => $disk,
                'folder' => $folderPath,
                'error' => $e->getMessage(),
            ]);
        }

        $driver = Storage::disk($disk)->getDriver();

        try {
            $driver->move($from, $to);
            if (Storage::disk($disk)->exists($to)) {
                return true;
            }
            Log::warning('Document reclassification primary move returned without raising but destination missing', [
                'document_id' => $documentId,
                'disk' => $disk,
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Document reclassification primary move failed, attempting copy+delete', [
                'document_id' => $documentId,
                'disk' => $disk,
                'from' => $from,
                'to' => $to,
                'error' => $this->describeThrowable($e),
            ]);
        }

        try {
            $driver->copy($from, $to);
            if (Storage::disk($disk)->exists($to)) {
                $this->safeDeleteSource($disk, $from, $documentId);

                return true;
            }
            Log::warning('Document reclassification copy returned without raising but destination missing', [
                'document_id' => $documentId,
                'disk' => $disk,
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Document reclassification driver copy failed, attempting stream copy', [
                'document_id' => $documentId,
                'disk' => $disk,
                'from' => $from,
                'to' => $to,
                'error' => $this->describeThrowable($e),
            ]);
        }

        // Final fallback: manual GetObject + PutObject + DeleteObject. This avoids the
        // Flysystem visibility/ACL pre-check that fails on Cloudflare R2 (R2 returns
        // 501 NotImplemented for GetObjectAcl), while still working on real S3.
        return $this->streamCopyAndDelete($disk, $from, $to, $documentId);
    }

    protected function streamCopyAndDelete(string $disk, string $from, string $to, int $documentId): bool
    {
        $stream = null;
        try {
            $stream = Storage::disk($disk)->readStream($from);
            if ($stream === false || !is_resource($stream)) {
                Log::warning('Document reclassification stream copy failed: cannot open source stream', [
                    'document_id' => $documentId,
                    'disk' => $disk,
                    'from' => $from,
                ]);

                return false;
            }

            $put = Storage::disk($disk)->put($to, $stream);
            if ($put === false) {
                Log::warning('Document reclassification stream copy failed: put returned false', [
                    'document_id' => $documentId,
                    'disk' => $disk,
                    'from' => $from,
                    'to' => $to,
                ]);

                return false;
            }

            if (!Storage::disk($disk)->exists($to)) {
                Log::warning('Document reclassification stream copy failed: destination missing after put', [
                    'document_id' => $documentId,
                    'disk' => $disk,
                    'from' => $from,
                    'to' => $to,
                ]);

                return false;
            }
        } catch (\Throwable $e) {
            Log::warning('Document reclassification stream copy threw', [
                'document_id' => $documentId,
                'disk' => $disk,
                'from' => $from,
                'to' => $to,
                'error' => $this->describeThrowable($e),
            ]);

            return false;
        } finally {
            if (is_resource($stream)) {
                @fclose($stream);
            }
        }

        $this->safeDeleteSource($disk, $from, $documentId);

        return true;
    }

    protected function safeDeleteSource(string $disk, string $from, int $documentId): void
    {
        try {
            Storage::disk($disk)->delete($from);
        } catch (\Throwable $e) {
            Log::warning('Document reclassification delete-source after copy failed (file still moved logically)', [
                'document_id' => $documentId,
                'disk' => $disk,
                'from' => $from,
                'error' => $this->describeThrowable($e),
            ]);
        }
    }

    /**
     * Build a flat string that walks the exception's `previous` chain so the
     * underlying SDK error (e.g. AwsException from S3) is captured in logs.
     */
    protected function describeThrowable(\Throwable $e, int $depth = 0): string
    {
        $line = get_class($e) . ': ' . $e->getMessage();
        $previous = $e->getPrevious();
        if ($previous && $depth < 5) {
            $line .= ' || caused by ' . $this->describeThrowable($previous, $depth + 1);
        }

        return $line;
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
