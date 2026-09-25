<?php

namespace App\Console\Commands;

use App\Services\PdfFirstPageOcrService;
use Illuminate\Console\Command;

class OcrStatusCommand extends Command
{
    protected $signature = 'documents:ocr-status';

    protected $description = 'Show whether local tools can read text from inside scanned PDFs.';

    public function handle(PdfFirstPageOcrService $ocr): int
    {
        $status = $ocr->toolStatus();

        $this->table(['Tool', 'Available'], [
            ['pdftotext (selectable PDF text)', $status['pdftotext'] ? 'yes' : 'no'],
            ['pdftoppm (render PDF pages)', $status['pdftoppm'] ? 'yes' : 'no'],
            ['ImageMagick / Imagick', $status['imagick'] ? 'yes' : 'no'],
            ['Tesseract binary', $status['tesseract'] ? 'yes' : 'no'],
            ['Node.js', $status['node'] ? 'yes' : 'no'],
            ['tesseract.js (Node OCR)', $status['tesseract_js'] ? 'yes' : 'no'],
        ]);

        if ($ocr->canOcrImages()) {
            $this->info('Keyword search can index words inside scanned PDFs.');
            $this->comment('Index existing files: php artisan documents:index-ocr --sync --force');
        } else {
            $this->error('No OCR engine found. Scanned PDFs will not match words like Phoenix.');
            $this->line('Install one of:');
            $this->line('  - npm install  (adds tesseract.js) and ensure node is on PATH');
            $this->line('  - Tesseract OCR (https://github.com/UB-Mannheim/tesseract/wiki)');
            $this->line('Then run: php artisan documents:prepare-ocr');
        }

        return $ocr->canOcrImages() ? self::SUCCESS : self::FAILURE;
    }
}
