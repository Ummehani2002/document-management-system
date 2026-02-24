<?php

namespace App\Services;

use Spatie\PdfToText\Pdf;

/**
 * Extract text from the first page of a PDF.
 * Uses pdftotext first; if the page is image-only (scanned), falls back to
 * rendering the page to an image and running Tesseract OCR.
 */
class PdfFirstPageOcrService
{
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

    /**
     * Render first PDF page to a PNG file. Tries pdftoppm (poppler) then ImageMagick convert.
     * Returns path to PNG or null.
     */
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
