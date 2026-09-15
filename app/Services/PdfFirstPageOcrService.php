<?php

namespace App\Services;

use Smalot\PdfParser\Parser as SmalotPdfParser;
use Spatie\PdfToText\Pdf;

/**
 * Extract searchable / classification text from PDFs.
 * Prefers pdftotext; falls back to PHP PdfParser, then Tesseract for image-only pages.
 */
class PdfFirstPageOcrService
{
    public const SEARCH_MAX_CHARS = 200000;

    public const SEARCH_MAX_PAGES = 12;

    /**
     * Broader extraction for keyword search (more pages + PHP fallback).
     */
    public function extractTextForSearch(string $pdfPath): string
    {
        $text = $this->extractWithPdftotextPageRange($pdfPath, 1, self::SEARCH_MAX_PAGES);
        if ($this->isUsableText($text)) {
            return $this->limitText($text);
        }

        $text = $this->extractWithSmalot($pdfPath);
        if ($this->isUsableText($text)) {
            return $this->limitText($text);
        }

        // Last resort: first-page OCR for scanned PDFs.
        return $this->limitText($this->extractFirstPageText($pdfPath));
    }

    /**
     * Text extraction used for classification:
     * first page attempts, then a broader whole-document parser fallback.
     */
    public function extractTextForClassification(string $pdfPath): string
    {
        // Prefer pages 1–2 together: many forms put the logo on page 1 and the real
        // title block on page 2; page 1 alone is often too short for classification.
        $text = $this->extractWithPdftotextPageRange($pdfPath, 1, 2);
        if (trim($text) !== '') {
            return $text;
        }

        $text = $this->extractWithPdftotextPageRange($pdfPath, 1, 3);
        if (trim($text) !== '') {
            return $text;
        }

        $text = $this->extractWithSmalot($pdfPath, 3);
        if (trim($text) !== '') {
            return $this->limitText($text, 20000);
        }

        // Scanned / image-only: render first page and run Tesseract (unchanged).
        return $this->extractFirstPageText($pdfPath);
    }

    /**
     * Extract text from the first page of the PDF at $pdfPath (local file path).
     * Returns the extracted text, or empty string if none could be extracted.
     */
    public function extractFirstPageText(string $pdfPath): string
    {
        $text = $this->extractWithPdftotext($pdfPath);

        if (trim($text) !== '') {
            return $text;
        }

        return $this->extractWithTesseractFallback($pdfPath);
    }

    protected function extractWithPdftotextPageRange(string $pdfPath, int $fromPage, int $toPage): string
    {
        try {
            return (new Pdf())
                ->setPdf($pdfPath)
                ->setOptions(['-f ' . max(1, $fromPage), '-l ' . max($fromPage, $toPage)])
                ->text();
        } catch (\Throwable $e) {
            \Log::debug('PdfFirstPageOcr: page-range pdftotext failed', ['path' => $pdfPath, 'error' => $e->getMessage()]);

            return '';
        }
    }

    protected function extractWithPdftotext(string $pdfPath): string
    {
        try {
            return (new Pdf())
                ->setPdf($pdfPath)
                ->setOptions(['-f 1', '-l 1'])
                ->text();
        } catch (\Throwable $e) {
            \Log::debug('PdfFirstPageOcr: pdftotext failed', ['path' => $pdfPath, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Pure-PHP fallback (works when pdftotext/poppler is not installed on the host).
     */
    protected function extractWithSmalot(string $pdfPath, ?int $maxPages = null): string
    {
        try {
            $parser = new SmalotPdfParser();
            $pdf = $parser->parseFile($pdfPath);
            $pages = $pdf->getPages();
            if ($pages === []) {
                return '';
            }

            $limit = $maxPages ?? self::SEARCH_MAX_PAGES;
            $chunks = [];
            foreach (array_slice($pages, 0, max(1, $limit)) as $page) {
                $chunks[] = (string) $page->getText();
            }

            return trim(implode("\n", $chunks));
        } catch (\Throwable $e) {
            \Log::debug('PdfFirstPageOcr: smalot pdfparser failed', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    protected function isUsableText(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        // Ignore tiny junk extractions.
        return mb_strlen(preg_replace('/\s+/', '', $trimmed) ?? '') >= 12;
    }

    protected function limitText(string $text, int $max = self::SEARCH_MAX_CHARS): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max);
    }

    /**
     * When pdftotext returns nothing, render first page to image and run Tesseract.
     * Requires: pdftoppm (poppler-utils) and tesseract on PATH.
     */
    protected function extractWithTesseractFallback(string $pdfPath): string
    {
        $tempDir = sys_get_temp_dir() . '/dms_ocr_' . substr(md5($pdfPath), 0, 8);
        if (!@mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            \Log::warning('PdfFirstPageOcr: could not create temp dir', ['dir' => $tempDir]);

            return '';
        }

        $imagePath = $tempDir . '/page';
        $cleanup = function () use ($tempDir) {
            if (!is_dir($tempDir)) {
                return;
            }
            foreach (glob($tempDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tempDir);
        };

        try {
            $png = $this->renderFirstPageToPng($pdfPath, $tempDir, $imagePath);
            if (!$png) {
                return '';
            }

            return $this->runTesseract($png);
        } finally {
            $cleanup();
        }
    }

    protected function renderFirstPageToPng(string $pdfPath, string $tempDir, string $imagePath): ?string
    {
        // 1) pdftoppm (poppler-utils)
        $cmd = sprintf(
            'pdftoppm -png -f 1 -l 1 -r 300 %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($imagePath)
        );
        exec($cmd, $out, $ret);
        if ($ret === 0) {
            $png = $imagePath . '-1.png';
            if (!file_exists($png)) {
                $png = $imagePath . '-01.png';
            }
            if (!file_exists($png)) {
                $found = glob($tempDir . '/*.png');
                $png = isset($found[0]) ? $found[0] : null;
            }
            if ($png && file_exists($png)) {
                return $png;
            }
        }

        // 2) ImageMagick: "convert" (ImageMagick 6) or "magick convert" (ImageMagick 7, e.g. Windows)
        $png = $tempDir . '/page.png';
        foreach (['convert', 'magick'] as $magickCmd) {
            $cmd = $magickCmd === 'magick'
                ? sprintf('magick %s[0] -density 300 %s 2>&1', escapeshellarg($pdfPath), escapeshellarg($png))
                : sprintf('convert %s[0] -density 300 %s 2>&1', escapeshellarg($pdfPath), escapeshellarg($png));
            exec($cmd, $out2, $ret2);
            if ($ret2 === 0 && file_exists($png)) {
                return $png;
            }
        }

        \Log::debug('PdfFirstPageOcr: could not render PDF to image (tried pdftoppm and ImageMagick)');

        return null;
    }

    protected function runTesseract(string $imagePath): string
    {
        $out = $imagePath . '_out';
        $cmd = sprintf(
            'tesseract %s %s -l eng 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($out)
        );
        exec($cmd, $output, $ret);
        $txtFile = $out . '.txt';
        if ($ret === 0 && file_exists($txtFile)) {
            $text = file_get_contents($txtFile);
            @unlink($txtFile);

            return $text ?: '';
        }
        \Log::debug('PdfFirstPageOcr: tesseract failed', ['return' => $ret, 'output' => implode("\n", $output ?? [])]);

        return '';
    }
}
