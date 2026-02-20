<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class ProcessOCR implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public $documentId;

    public function __construct($documentId)
    {
        $this->documentId = $documentId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            return;
        }

        $disk = config('filesystems.default');
        if (! Storage::disk($disk)->exists($document->file_path)) {
            \Log::warning('ProcessOCR: file not found', ['path' => $document->file_path]);
            return;
        }

        $tempPath = null;
        try {
            // PdfToText requires a local file path; stream from disk to temp for local or S3
            $tempPath = tempnam(sys_get_temp_dir(), 'dms_ocr_') . '.pdf';
            file_put_contents($tempPath, Storage::disk($disk)->get($document->file_path));

            $text = (new Pdf())
                ->setPdf($tempPath)
                ->setOptions(['-f 1', '-l 1'])
                ->text();

            $document->update(['ocr_text' => $text]);
        } catch (\Throwable $e) {
            \Log::error('ProcessOCR failed: ' . $e->getMessage(), ['document_id' => $document->id]);
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}