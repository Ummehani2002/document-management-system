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

    protected $description = 'Index first page of PDFs: dispatch OCR for documents with empty ocr_text, or run sync to process now.';

    public function handle(): int
    {
        $query = Document::query();

        $ids = (array) $this->option('id');
        if (!empty($ids)) {
            $query->whereIn('id', array_map('intval', $ids));
        }
        if ($projectId = $this->option('project')) {
            $query->where('project_id', (int) $projectId);
        }

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('ocr_text')->orWhere('ocr_text', '');
            });
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info('No documents need indexing. Use --force to re-process documents that already have text.');
            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info("Processing {$count} document(s) now (sync)...");
            $query->pluck('id')->each(function ($id) {
                try {
                    $job = new ProcessOCR($id);
                    $job->handle();
                    $doc = Document::find($id);
                    $hasText = $doc && trim((string) $doc->ocr_text) !== '';
                    if ($hasText) {
                        $this->line("  Indexed document id: {$id}");
                    } else {
                        $this->warn("  Document id: {$id} — no text extracted (first page may be image-only/scanned).");
                    }
                } catch (\Throwable $e) {
                    $this->warn("  Failed document id {$id}: " . $e->getMessage());
                }
            });
            $this->info('Done.');
        } else {
            $query->pluck('id')->each(fn ($id) => ProcessOCR::dispatch($id));
            $this->info("Dispatched {$count} job(s). Run php artisan queue:work to process them.");
        }

        $this->comment('Tip: run php artisan documents:reclassify --project=… or --id=… to move rows into the folder suggested by OCR.');

        return self::SUCCESS;
    }
}
