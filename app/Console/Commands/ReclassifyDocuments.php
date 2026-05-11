<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentReclassificationService;
use Illuminate\Console\Command;

class ReclassifyDocuments extends Command
{
    protected $signature = 'documents:reclassify
        {--id=* : Reclassify only these document IDs (repeatable)}
        {--project= : Limit to a specific project_id}
        {--entity= : Limit to a specific entity_id}
        {--folder= : Limit to documents currently in this document_type (e.g. "Other")}
        {--metadata-only : Only update the document_type column in the database; do not move the underlying file on storage}
        {--min-confidence= : Minimum confidence (0..1) required to update; defaults to DOC_AUTO_CLASSIFY_MIN_CONFIDENCE or 0.70}
        {--dry-run : List documents that would be processed; do not modify}';

    protected $description = 'Re-run filename/OCR-based reclassification on existing documents and (optionally) move files to the correct folder.';

    public function handle(DocumentReclassificationService $service): int
    {
        $query = Document::query();

        $ids = (array) $this->option('id');
        if (!empty($ids)) {
            $query->whereIn('id', array_map('intval', $ids));
        }
        if ($projectId = $this->option('project')) {
            $query->where('project_id', (int) $projectId);
        }
        if ($entityId = $this->option('entity')) {
            $query->where('entity_id', (int) $entityId);
        }
        if ($folder = $this->option('folder')) {
            $query->where('document_type', (string) $folder);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No documents found for the given filters.');
            return self::SUCCESS;
        }

        $metadataOnly = (bool) $this->option('metadata-only');
        $dryRun = (bool) $this->option('dry-run');
        $minConfidenceOpt = $this->option('min-confidence');
        $minConfidence = is_numeric($minConfidenceOpt)
            ? (float) $minConfidenceOpt
            : (float) env('DOC_AUTO_CLASSIFY_MIN_CONFIDENCE', 0.70);

        $mode = $metadataOnly ? 'metadata-only (no file move)' : 'full (DB + file move)';
        $this->info("Reclassifying {$total} document row(s) in {$mode} mode; threshold={$minConfidence}");

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $query->orderBy('id')->chunkById(100, function ($rows) use ($service, $metadataOnly, $minConfidence, &$processed, &$skipped, &$failed, $dryRun) {
            foreach ($rows as $doc) {
                $label = "id={$doc->id} type=\"{$doc->document_type}\" name=\"{$doc->file_name}\"";

                if ($metadataOnly) {
                    $result = DocumentFilenameParser::classifyForAutomation(
                        (string) $doc->file_name,
                        (string) ($doc->ocr_text ?? '')
                    );
                    $newType = (string) ($result['document_category'] ?? '');
                    $conf = (float) ($result['confidence'] ?? 0);
                    if ($newType === '' || $newType === 'Other' || $conf < $minConfidence) {
                        $this->line("  SKIP     {$label} -> \"{$newType}\" (conf=" . number_format($conf, 2) . ")");
                        $skipped++;
                        continue;
                    }
                    if ($newType === (string) $doc->document_type) {
                        $this->line("  UNCHANGED {$label} (already \"{$newType}\")");
                        $skipped++;
                        continue;
                    }
                    if ($dryRun) {
                        $this->line("  WOULD SET {$label} -> \"{$newType}\" (conf=" . number_format($conf, 2) . ")");
                        $processed++;
                        continue;
                    }
                    try {
                        Document::whereKey($doc->id)->update(['document_type' => $newType]);
                        $this->line("  UPDATED  {$label} -> \"{$newType}\" (conf=" . number_format($conf, 2) . ")");
                        $processed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->warn("  FAILED   {$label} - " . $e->getMessage());
                    }
                    continue;
                }

                if ($dryRun) {
                    $this->line("  WOULD RECLASSIFY {$label}");
                    $processed++;
                    continue;
                }

                try {
                    $service->refineAfterOcr($doc->fresh() ?: $doc);
                    $after = Document::find($doc->id);
                    $newType = $after?->document_type ?? '?';
                    $this->line("  PROCESSED {$label} -> \"{$newType}\"");
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("  FAILED   {$label} - " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry run complete. Would process {$processed} document(s); skipped {$skipped}.");
        } else {
            $this->info("Done. Processed {$processed} document(s); skipped {$skipped}; failed {$failed}.");
        }

        return self::SUCCESS;
    }
}
