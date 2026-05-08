<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentLocationResolver;
use Illuminate\Console\Command;

class CleanupOrphanDocuments extends Command
{
    protected $signature = 'documents:cleanup-orphans
        {--dry-run : Only list orphan rows; do not delete}
        {--project= : Limit to a specific project_id}
        {--entity= : Limit to a specific entity_id}';

    protected $description = 'Delete document DB rows whose underlying file is missing from storage (orphans).';

    public function handle(): int
    {
        $query = Document::query()->select(['id', 'entity_id', 'project_id', 'file_name', 'file_path']);

        if ($projectId = $this->option('project')) {
            $query->where('project_id', (int) $projectId);
        }
        if ($entityId = $this->option('entity')) {
            $query->where('entity_id', (int) $entityId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No documents found for the given filters.');
            return self::SUCCESS;
        }

        $this->info("Scanning {$total} document row(s)...");

        $dryRun = (bool) $this->option('dry-run');
        $orphans = 0;
        $deleted = 0;
        $failed = 0;

        $query->orderBy('id')->chunkById(200, function ($rows) use (&$orphans, &$deleted, &$failed, $dryRun) {
            foreach ($rows as $doc) {
                $location = DocumentLocationResolver::resolve((string) $doc->file_path);
                if ($location !== null) {
                    continue;
                }

                $orphans++;
                $label = "id={$doc->id} name=\"{$doc->file_name}\" path=\"{$doc->file_path}\"";
                if ($dryRun) {
                    $this->line("  ORPHAN {$label}");
                    continue;
                }

                try {
                    Document::whereKey($doc->id)->delete();
                    $deleted++;
                    $this->line("  DELETED {$label}");
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("  FAILED  {$label} - " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry run complete. Found {$orphans} orphan row(s).");
        } else {
            $this->info("Done. Deleted {$deleted} orphan row(s); skipped/failed {$failed}.");
        }

        return self::SUCCESS;
    }
}
