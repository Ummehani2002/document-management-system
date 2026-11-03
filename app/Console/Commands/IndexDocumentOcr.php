<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOCR;
use App\Models\Document;
use App\Services\DocumentFilenameParser;
use Illuminate\Console\Command;

class IndexDocumentOcr extends Command
{
    protected $signature = 'documents:index-ocr
        {--sync : Run OCR synchronously instead of dispatching to queue}
        {--force : Re-run OCR even when ocr_text is already stored}
        {--project= : Limit to project_id}
        {--id=* : Limit to document id(s), repeatable}
        {--limit= : Max documents to process in this run}
        {--max-seconds= : Stop after this many seconds (remaining are skipped, not queued)}';

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

        // Newest first so recent MIR/IR uploads get Subject filled during the deploy window.
        $idsToProcess = $query->orderByDesc('id')->pluck('id');
        if (! $this->option('force') && empty($ids) && ! $this->option('project')) {
            $idsToProcess = $this->idsMissingFormSubject()
                ->merge($idsToProcess)
                ->unique()
                ->values();
        }
        $count = $idsToProcess->count();
        if ($count === 0) {
            $this->info('No documents need indexing. Use --force to re-process documents that already have text.');

            return self::SUCCESS;
        }

        $limit = max(0, (int) $this->option('limit'));
        $maxSeconds = $this->option('max-seconds');
        $maxSeconds = $maxSeconds === null || $maxSeconds === false || $maxSeconds === ''
            ? ($this->option('sync') && ! $this->option('force') ? 180 : 0)
            : max(0, (int) $maxSeconds);

        if ($this->option('sync')) {
            config(['ocr.search_pages' => 1]);
            $this->info("Processing {$count} document(s) now (sync, newest first)...");
            $ok = 0;
            $empty = 0;
            $failed = 0;
            $skipped = 0;
            $started = microtime(true);

            foreach ($idsToProcess as $id) {
                $timedOut = $maxSeconds > 0 && (microtime(true) - $started) >= $maxSeconds;
                $hitLimit = $limit > 0 && ($ok + $empty + $failed) >= $limit;
                if ($timedOut || $hitLimit) {
                    $skipped++;

                    continue;
                }

                try {
                    (new ProcessOCR((int) $id))->handle();
                    $doc = Document::find($id);
                    $hasText = $doc && trim((string) $doc->ocr_text) !== '';
                    if ($hasText) {
                        $ok++;
                        $meta = DocumentFilenameParser::extractReferenceAndSubject(
                            (string) $doc->ocr_text,
                            (string) $doc->file_name
                        );
                        $subject = $meta['subject'] ?? '—';
                        $this->line("  Indexed document id: {$id} subject=".mb_substr($subject, 0, 80));
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

            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} remaining document(s) (time/limit). Re-run: php artisan documents:index-ocr --sync");
            }
            $this->info("Done. Indexed={$ok}, empty={$empty}, failed={$failed}, skipped={$skipped}.");
        } else {
            foreach ($idsToProcess as $id) {
                ProcessOCR::dispatch((int) $id);
            }
            $this->info("Dispatched {$count} job(s). Run php artisan queue:work to process them.");
        }

        $this->comment('Tip: run php artisan documents:reclassify to move rows into the folder suggested by OCR.');

        return self::SUCCESS;
    }

    /**
     * Forms whose stored text is missing, or labels-only (no Title/Description / Material Description).
     * Newest first.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function idsMissingFormSubject()
    {
        $ids = collect();

        // Newest first; cap so deploy stays within the Cloud time budget.
        Document::query()
            ->orderByDesc('id')
            ->limit(400)
            ->get(['id', 'file_name', 'ocr_text'])
            ->each(function (Document $doc) use ($ids) {
                if ($this->needsFormSubjectOcr($doc)) {
                    $ids->push((int) $doc->id);
                }
            });

        return $ids->unique()->values();
    }

    protected function needsFormSubjectOcr(Document $doc): bool
    {
        $ocr = trim((string) $doc->ocr_text);
        $haystack = strtoupper($doc->file_name.' '.$ocr);
        $looksLikeForm = (bool) preg_match(
            '/(?:^|[-_\s])(?:IR|MAR|MIR|WIR|MS|MAS|MAT|MST|SD|SDR)(?:[-_\s]|$)|TITLE.{0,20}(?:DESCRIPTION|SUBJECT)|DESCRIPTION OF ITEM|DESCRIPTION OF MATERIAL|MATERIAL DESCRIPTION|MATERIAL INSPECTION REQUEST|WORK INSPECTION REQUEST|MATERIAL SUBMITTAL|TECHNICAL SUBMISSION|MATERIAL APPROVAL/i',
            $haystack
        );
        if (! $looksLikeForm) {
            return false;
        }

        if ($ocr === '') {
            return true;
        }

        $fromInside = DocumentFilenameParser::extractReferenceAndSubject($ocr, 'document.pdf');

        return ($fromInside['subject'] ?? '—') === '—';
    }
}
