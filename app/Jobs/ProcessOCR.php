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

    public $timeout = 600;

    public function __construct($documentId, bool $preserveFolder = false)
    {
        $this->documentId = $documentId;
        $this->preserveFolder = $preserveFolder;
    }

    /** Extract searchable text and optionally auto-classify folder. */
    public function handle(): void
    {
        @ini_set('memory_limit', '1024M');

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
            // Best-effort text extraction (pdftotext / Smalot / Tesseract when available).
            try {
                $ext = strtolower(pathinfo((string) $document->file_name, PATHINFO_EXTENSION));
                if ($ext === '') {
                    $ext = 'tmp';
                }
                $tempPath = tempnam(sys_get_temp_dir(), 'dms_ocr_').'.'.$ext;
                file_put_contents($tempPath, Storage::disk($disk)->get($document->file_path));

                $text = '';
                if ($ext === 'pdf') {
                    // Classification path OCRs page 1 of Sharp/scanned forms (Flate+DCT JPEGs).
                    $ocr = app(PdfFirstPageOcrService::class);
                    $text = $ocr->extractTextForClassification($tempPath);
                    if (trim($text) === '') {
                        $text = $ocr->extractTextForSearch($tempPath);
                    }
                } elseif (in_array($ext, ['docx', 'xlsx', 'doc', 'xls'], true)) {
                    $text = app(OfficeDocumentTextExtractionService::class)->extractText($tempPath, $ext);
                }

                $existing = trim((string) ($document->ocr_text ?? ''));
                $text = trim((string) $text);
                // Never wipe browser/upload OCR with an empty Cloud extraction.
                if ($text === '' && $existing !== '') {
                    $text = $existing;
                } elseif ($text !== '' && $existing !== '' && strlen($existing) > strlen($text)) {
                    $existingHasSubject = app(\App\Services\PdfFirstPageOcrService::class)
                        ->textYieldsFormSubject($existing);
                    $newHasSubject = app(\App\Services\PdfFirstPageOcrService::class)
                        ->textYieldsFormSubject($text);
                    if ($existingHasSubject && ! $newHasSubject) {
                        $text = $existing;
                    }
                }

                $document->update(['ocr_text' => $text !== '' ? $text : null]);
                \Log::info('ProcessOCR stored text', [
                    'document_id' => $document->id,
                    'chars' => strlen($text),
                    'has_title_subject' => (bool) preg_match('/Title\s*[\/&]\s*Subject|Title\s*&\s*Description|Material\s+Description/i', $text),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('ProcessOCR text extraction failed (folder will use whatever text is already stored)', [
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
            gc_collect_cycles();
        }
    }
}
