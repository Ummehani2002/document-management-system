<?php

namespace App\Services;

use App\Jobs\ProcessOCR;
use App\Models\Document;
use App\Services\UserActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentFileReplacer
{
    /**
     * Overwrite the stored file for an existing document (same DB row and storage key).
     */
    public function replace(Document $document, UploadedFile $file): void
    {
        $path = (string) $document->file_path;
        $location = DocumentLocationResolver::resolve($path);

        if ($location === null) {
            $stored = ltrim(str_replace('\\', '/', $path), '/');
            if ($stored === '' || ! str_starts_with($stored, 'documents/')) {
                throw new \RuntimeException('File not found in storage and no valid path on record.');
            }
            $disk = (string) config('filesystems.default', 'local');
            $location = ['source' => 'disk', 'disk' => $disk, 'path' => $stored];
        }

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        try {
            if ($location['source'] === 'disk') {
                $written = Storage::disk($location['disk'])->put($location['path'], $stream);
                if ($written === false) {
                    throw new \RuntimeException('Could not write the file to cloud storage.');
                }
            } else {
                $bytes = stream_get_contents($stream);
                if ($bytes === false) {
                    throw new \RuntimeException('Could not read the uploaded file.');
                }
                if (@file_put_contents($location['path'], $bytes) === false) {
                    throw new \RuntimeException('Could not overwrite the file on disk.');
                }
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $document->ocr_text = null;
        $document->modified_by_user_id = Auth::id();
        $document->save();

        UserActivityLogger::replaced($document);

        $this->dispatchProcessOcr($document->id);
    }

    /**
     * Store an uploaded revision onto the existing project row (same logical file).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function replaceIncomingUpload(
        Document $document,
        UploadedFile $file,
        string $storedFileName,
        string $folderPath,
        string $disk,
        array $attributes = [],
        bool $preserveFolder = false
    ): bool {
        try {
            $path = $file->storeAs($folderPath, $storedFileName, $disk);
        } catch (\Throwable $e) {
            Log::warning('Document family replace failed: storage write exception', [
                'disk' => $disk,
                'document_id' => $document->id,
                'stored_file_name' => $storedFileName,
                'target_path' => $folderPath.'/'.$storedFileName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! is_string($path) || trim($path) === '') {
            Log::warning('Document family replace failed: empty storage path returned', [
                'disk' => $disk,
                'document_id' => $document->id,
                'stored_file_name' => $storedFileName,
                'target_path' => $folderPath.'/'.$storedFileName,
            ]);

            return false;
        }

        $this->adoptStoredPath($document, $path, $storedFileName, $attributes, $preserveFolder);

        return true;
    }

    /**
     * Point an existing row at a file already written to storage, then drop extra family copies.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function adoptStoredPath(
        Document $document,
        string $path,
        string $storedFileName,
        array $attributes = [],
        bool $preserveFolder = false
    ): void {
        $oldPath = (string) $document->file_path;

        foreach (['entity_id', 'project_id', 'discipline', 'document_type'] as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== null) {
                $document->{$field} = $attributes[$field];
            }
        }

        $document->file_name = $storedFileName;
        $document->file_path = $path;
        $document->ocr_text = null;
        $document->modified_by_user_id = Auth::id();
        $document->save();

        if ($oldPath !== '' && $oldPath !== $path) {
            $this->deleteStoredPath($oldPath);
        }

        $this->retireOtherFamilyMembers($document);

        UserActivityLogger::replaced($document, [
            'replaced_same_project_file' => true,
        ]);

        $this->dispatchProcessOcr($document->id, $preserveFolder);
    }

    public function retireOtherFamilyMembers(Document $survivor): int
    {
        $removed = 0;
        $deletions = app(DocumentDeletionService::class);

        foreach (DocumentFileVersioning::versionFamilyDocuments($survivor) as $row) {
            if ((int) $row->id === (int) $survivor->id) {
                continue;
            }
            $deletions->delete($row, ['reason' => 'replaced_by_same_document']);
            $removed++;
        }

        return $removed;
    }

    protected function deleteStoredPath(string $path): void
    {
        $location = DocumentLocationResolver::resolve($path);
        if ($location === null) {
            return;
        }

        if (($location['source'] ?? '') === 'disk') {
            Storage::disk($location['disk'])->delete($location['path']);

            return;
        }

        @unlink($location['path']);
    }

    protected function dispatchProcessOcr(int $documentId, bool $preserveFolder = false): void
    {
        $inline = config('queue.default') === 'sync'
            || filter_var(env('DMS_OCR_SYNC_ON_UPLOAD', false), FILTER_VALIDATE_BOOL);

        try {
            if ($inline) {
                (new ProcessOCR($documentId, $preserveFolder))->handle();

                return;
            }
            ProcessOCR::dispatch($documentId, $preserveFolder)->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('ProcessOCR after file replace failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
