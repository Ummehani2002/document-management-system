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

    protected function dispatchProcessOcr(int $documentId): void
    {
        $inline = config('queue.default') === 'sync'
            || filter_var(env('DMS_OCR_SYNC_ON_UPLOAD', false), FILTER_VALIDATE_BOOL);

        try {
            if ($inline) {
                (new ProcessOCR($documentId))->handle();

                return;
            }
            ProcessOCR::dispatch($documentId)->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('ProcessOCR after file replace failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
