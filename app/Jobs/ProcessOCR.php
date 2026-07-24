<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentReclassificationService;
use App\Services\OfficeDocumentTextExtractionService;
use App\Services\PdfFirstPageOcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessOCR implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $documentId;

    /**
     * When true (manual upload), keep the chosen folder — only extract searchable OCR text.
     */
    public bool $preserveFolder;

    public function __construct($documentId, bool $preserveFolder = false)
    {
        $this->documentId = $documentId;
        $this->preserveFolder = $preserveFolder;
    }

    /** Extract searchable text and optionally auto-classify folder. */
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
            // Best-effort text extraction. Classification must still run when this fails
            // (e.g. Tesseract/poppler missing, image-only PDFs) so filename-based
            // auto-classification can still move "SD-...", "DT-...", etc. into the
            // correct folder instead of leaving every upload stuck in "Other".
            try {
                $ext = strtolower(pathinfo((string) $document->file_name, PATHINFO_EXTENSION));
                if ($ext === '') {
                    $ext = 'tmp';
                }
                $tempPath = tempnam(sys_get_temp_dir(), 'dms_ocr_').'.'.$ext;
                file_put_contents($tempPath, Storage::disk($disk)->get($document->file_path));

                $text = '';
                if ($ext === 'pdf') {
                    $text = app(PdfFirstPageOcrService::class)->extractTextForClassification($tempPath);
                } elseif (in_array($ext, ['docx', 'xlsx', 'doc', 'xls'], true)) {
                    $text = app(OfficeDocumentTextExtractionService::class)->extractText($tempPath, $ext);
                }

                $document->update(['ocr_text' => $text]);
            } catch (\Throwable $e) {
                \Log::warning('ProcessOCR text extraction failed (classification will still run by filename)', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($this->preserveFolder) {
                \Log::info('ProcessOCR skipped folder reclassification (manual upload folder locked)', [
                    'document_id' => $document->id,
                    'document_type' => $document->document_type,
                ]);

                return;
            }

            app(DocumentReclassificationService::class)->refineAfterOcr($document);
        } catch (\Throwable $e) {
            \Log::error('ProcessOCR pipeline failed: '.$e->getMessage(), ['document_id' => $document->id]);
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
