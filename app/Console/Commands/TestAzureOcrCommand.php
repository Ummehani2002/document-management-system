<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\AzureDocumentIntelligenceService;
use App\Services\PdfFirstPageOcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestAzureOcrCommand extends Command
{
    protected $signature = 'documents:test-azure-ocr
        {--id= : Document id to test (defaults to first PDF without ocr_text)}';

    protected $description = 'Diagnose Azure OCR against one document and print API errors.';

    public function handle(AzureDocumentIntelligenceService $azure, PdfFirstPageOcrService $local): int
    {
        if (! $azure->enabled()) {
            $this->error('Azure OCR is not configured. Set AZURE_AI_ENDPOINT and AZURE_AI_KEY.');

            return self::FAILURE;
        }

        $this->info('Endpoint: '.rtrim((string) config('services.azure_ai.endpoint'), '/'));
        $this->info('Key configured: yes (length '.strlen((string) config('services.azure_ai.key')).')');
        $this->info('Max bytes: '.(int) config('services.azure_ai.max_bytes'));

        $id = $this->option('id');
        $query = Document::query()->orderBy('id');
        if ($id) {
            $query->where('id', (int) $id);
        } else {
            $query->where(function ($q) {
                $q->whereNull('ocr_text')->orWhere('ocr_text', '');
            });
        }

        $document = $query->first();
        if (! $document) {
            $this->error('No document found to test.');

            return self::FAILURE;
        }

        $this->info("Testing document id={$document->id} name=\"{$document->file_name}\"");

        $disk = config('filesystems.default');
        if (! Storage::disk($disk)->exists($document->file_path)) {
            $this->error('File missing on disk: '.$document->file_path);

            return self::FAILURE;
        }

        $size = (int) Storage::disk($disk)->size($document->file_path);
        $this->line('Storage bytes: '.$size);

        try {
            // Local extractor on a small sample only when file is manageable.
            if ($size <= 25 * 1024 * 1024) {
                $ext = strtolower(pathinfo((string) $document->file_name, PATHINFO_EXTENSION)) ?: 'pdf';
                $tempPath = tempnam(sys_get_temp_dir(), 'dms_azure_test_').'.'.$ext;
                file_put_contents($tempPath, Storage::disk($disk)->get($document->file_path));
                $localText = $local->extractTextForSearch($tempPath);
                @unlink($tempPath);
                $this->line('Local extractor chars: '.strlen(trim($localText)));
            } else {
                $this->line('Local extractor skipped (file > 25MB).');
            }

            $azureText = $azure->extractTextFromStorage($disk, (string) $document->file_path, 'application/pdf');
            $this->line('Azure extractor chars: '.strlen(trim($azureText)));

            if (trim($azureText) !== '') {
                $this->info('SUCCESS — sample text:');
                $this->line(mb_substr(preg_replace('/\s+/', ' ', $azureText) ?? $azureText, 0, 400));

                return self::SUCCESS;
            }

            $this->error('Azure returned no text.');
            foreach ($azure->lastErrors() as $err) {
                $this->warn('  - '.$err);
            }
            $this->comment('If you see 404: create a Document Intelligence resource (or enable Form Recognizer) on this endpoint.');
            $this->comment('If you see 401: regenerate the key and update AZURE_AI_KEY.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
