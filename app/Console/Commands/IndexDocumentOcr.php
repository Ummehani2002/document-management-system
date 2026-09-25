<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOCR;
use App\Models\Document;
use Illuminate\Console\Command;

class IndexDocumentOcr extends Command
{
    protected $signature = 'documents:index-ocr
        {--sync : Run OCR synchronously instead of dispatching to queue}
        {--force : Re-run OCR even when ocr_text is already stored}
        {--project= : Limit to project_id}
        {--id=* : Limit to document id(s), repeatable}';

    protected $description = 'Index PDF/Office text into ocr_text so keyword search can match words inside the file.';

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');

        $ocr = app(\App\Services\PdfFirstPageOcrService::class);
        $status = $ocr->toolStatus();
        if ($ocr->canOcrImages()) {
            $engine = $status['tesseract'] ? 'Tesseract' : 'tesseract.js';
            $this->info("OCR engine ready ({$engine}). Scanned PDFs will be read for keyword search.");
        } else {
            $this->warn('No OCR engine found. Selectable-text PDFs will still be indexed; scanned PDFs need Tesseract or `npm install`.');
        }

        $query = Document::query();

        $ids = (array) $this->option('id');
        if (! empty($ids)) {
            $query->whereIn('id', array_map('intval', $ids));
        }
        if ($projectId = $this->option('project')) {
            $query->where('project_id', (int) $projectId);
        }

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('ocr_text')->orWhere('ocr_text', '');
            });
        }

        $idsToProcess = $query->orderBy('id')->pluck('id');
        $count = $idsToProcess->count();
        if ($count === 0) {
            $this->info('No documents need indexing. Use --force to re-process documents that already have text.');

            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info("Processing {$count} document(s) now (sync)...");
            $ok = 0;
            $empty = 0;
            $failed = 0;

            foreach ($idsToProcess as $id) {
                try {
                    (new ProcessOCR((int) $id))->handle();
                    $doc = Document::find($id);
                    $hasText = $doc && trim((string) $doc->ocr_text) !== '';
                    if ($hasText) {
                        $ok++;
                        $this->line("  Indexed document id: {$id}");
                    } else {
                        $empty++;
                        $this->warn("  Document id: {$id} — no text extracted (scanned PDF / OCR tools unavailable).");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("  Failed document id {$id}: ".$e->getMessage());
                }

                gc_collect_cycles();
            }

            $this->info("Done. Indexed={$ok}, empty={$empty}, failed={$failed}.");
        } else {
            foreach ($idsToProcess as $id) {
                ProcessOCR::dispatch((int) $id);
            }
            $this->info("Dispatched {$count} job(s). Run php artisan queue:work to process them.");
        }

        $this->comment('Tip: run php artisan documents:reclassify to move rows into the folder suggested by OCR.');

        return self::SUCCESS;
    }
}
