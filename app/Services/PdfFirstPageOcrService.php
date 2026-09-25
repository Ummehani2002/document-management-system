<?php

namespace App\Services;

use Smalot\PdfParser\Parser as SmalotPdfParser;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Extract searchable / classification text from PDFs.
 * Prefers pdftotext; falls back to PHP PdfParser, then local OCR
 * (Tesseract binary or Node tesseract.js) for image-only pages.
 */
class PdfFirstPageOcrService
{
    public const SEARCH_MAX_CHARS = 500000;

    /** Pages for Smalot fallback (pdftotext uses the whole document when available). */
    public const SEARCH_MAX_PAGES = 80;

    /** Skip Smalot above this size — it loads the whole PDF and can OOM on Cloud. */
    public const SMALOT_MAX_BYTES = 5_000_000;

    /**
     * Broader extraction for keyword search (full doc + OCR when the text layer is thin).
     */
    public function extractTextForSearch(string $pdfPath): string
    {
        $layer = $this->extractEmbeddedText($pdfPath);

        if ($this->isUsableText($layer) && ! $this->shouldSupplementWithOcr($layer)) {
            return $this->limitText($layer);
        }

        $ocrPages = max(1, (int) config('ocr.search_pages', 10));
        $ocr = $this->extractWithLocalOcr($pdfPath, $ocrPages);

        return $this->limitText($this->mergeText($layer, $ocr));
    }

    /**
     * Text extraction used for classification:
     * first page attempts, then a broader whole-document parser fallback.
     */
    public function extractTextForClassification(string $pdfPath): string
    {
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

        return $this->extractFirstPageText($pdfPath);
    }

    /**
     * Extract text from the first page of the PDF at $pdfPath (local file path).
     */
    public function extractFirstPageText(string $pdfPath): string
    {
        $text = $this->extractWithPdftotext($pdfPath);

        if (trim($text) !== '') {
            return $text;
        }

        return $this->extractWithLocalOcr($pdfPath, 1);
    }

    /**
     * True when a thin/junk text layer should not block real OCR of the scan.
     */
    public function shouldSupplementWithOcr(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return true;
        }

        $minChars = max(50, (int) config('ocr.min_text_layer_chars', 400));
        $compact = preg_replace('/\s+/', '', $trimmed) ?? '';
        if (mb_strlen($compact) < $minChars) {
            return true;
        }

        preg_match_all('/[A-Za-z]{3,}/u', $trimmed, $words);

        return count($words[0] ?? []) < 12;
    }

    /**
     * @return array{pdftotext: bool, pdftoppm: bool, tesseract: bool, node: bool, tesseract_js: bool, imagick: bool}
     */
    public function toolStatus(): array
    {
        return [
            'pdftotext' => $this->binary('pdftotext', 'OCR_PDFTOTEXT_BINARY', 'pdftotext_binary') !== null,
            'pdftoppm' => $this->binary('pdftoppm', 'OCR_PDFTOPPM_BINARY', 'pdftoppm_binary') !== null,
            'tesseract' => $this->tesseractBinary() !== null,
            'node' => $this->nodeBinary() !== null,
            'tesseract_js' => is_file(base_path('node_modules/tesseract.js/package.json')),
            'imagick' => class_exists(\Imagick::class),
        ];
    }

    public function canOcrImages(): bool
    {
        if ($this->tesseractBinary() !== null) {
            return true;
        }

        return $this->nodeBinary() !== null
            && is_file(base_path('node_modules/tesseract.js/package.json'));
    }

    protected function extractEmbeddedText(string $pdfPath): string
    {
        $text = $this->extractWithPdftotextAll($pdfPath);
        if ($this->isUsableText($text) && ! $this->shouldSupplementWithOcr($text)) {
            return $text;
        }

        $range = $this->extractWithPdftotextPageRange($pdfPath, 1, self::SEARCH_MAX_PAGES);
        if ($this->isUsableText($range) && mb_strlen($range) > mb_strlen($text)) {
            $text = $range;
        }

        $smalot = $this->extractWithSmalot($pdfPath);
        if ($this->isUsableText($smalot) && mb_strlen($smalot) > mb_strlen($text)) {
            $text = $smalot;
        }

        return $text;
    }

    protected function extractWithLocalOcr(string $pdfPath, int $maxPages): string
    {
        if (! $this->canOcrImages()) {
            \Log::info('PdfOcr: no local OCR engine (install tesseract or npm tesseract.js)');

            return '';
        }

        $tempDir = sys_get_temp_dir().'/dms_ocr_'.substr(md5($pdfPath.microtime(true)), 0, 10);
        if (! @mkdir($tempDir, 0700, true) && ! is_dir($tempDir)) {
            return '';
        }

        $cleanup = function () use ($tempDir) {
            if (! is_dir($tempDir)) {
                return;
            }
            foreach (glob($tempDir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tempDir);
        };

        try {
            $pages = max(1, min(20, $maxPages));
            $chunks = [];

            for ($page = 1; $page <= $pages; $page++) {
                $imageBase = $tempDir.'/page'.$page;
                $png = $this->renderPageToPng($pdfPath, $tempDir, $imageBase, $page);
                if (! $png) {
                    break;
                }
                $text = $this->ocrImage($png);
                if (trim($text) !== '') {
                    $chunks[] = $text;
                }
            }

            if ($chunks !== []) {
                return trim(implode("\n\n", $chunks));
            }

            // Scanned PDFs often embed one JPEG per page — OCR those without pdftoppm/Ghostscript.
            foreach ($this->extractEmbeddedJpegs($pdfPath, $tempDir, $pages) as $jpeg) {
                $text = $this->ocrImage($jpeg);
                if (trim($text) !== '') {
                    $chunks[] = $text;
                }
            }

            return trim(implode("\n\n", $chunks));
        } finally {
            $cleanup();
        }
    }

    protected function ocrImage(string $imagePath): string
    {
        $tesseract = $this->tesseractBinary();
        if ($tesseract) {
            $out = $imagePath.'_out';
            $process = new Process([$tesseract, $imagePath, $out, '-l', 'eng']);
            $process->setTimeout(120);
            $process->run();
            $txtFile = $out.'.txt';
            if ($process->isSuccessful() && is_file($txtFile)) {
                $text = (string) file_get_contents($txtFile);
                @unlink($txtFile);

                return $text;
            }
        }

        $node = $this->nodeBinary();
        $script = base_path('scripts/ocr-image.mjs');
        if ($node && is_file($script) && is_file(base_path('node_modules/tesseract.js/package.json'))) {
            $process = new Process([$node, $script, $imagePath], base_path());
            $process->setTimeout(180);
            $process->run();
            if ($process->isSuccessful()) {
                return $process->getOutput();
            }
            \Log::debug('PdfOcr: tesseract.js failed', ['err' => $process->getErrorOutput()]);
        }

        return '';
    }

    protected function extractWithPdftotextPageRange(string $pdfPath, int $fromPage, int $toPage): string
    {
        try {
            return $this->makePdfToText()
                ->setPdf($pdfPath)
                ->setOptions(['-f '.max(1, $fromPage), '-l '.max($fromPage, $toPage)])
                ->text();
        } catch (\Throwable $e) {
            \Log::debug('PdfFirstPageOcr: page-range pdftotext failed', ['path' => $pdfPath, 'error' => $e->getMessage()]);

            return '';
        }
    }

    protected function extractWithPdftotextAll(string $pdfPath): string
    {
        try {
            return $this->makePdfToText()->setPdf($pdfPath)->text();
        } catch (\Throwable $e) {
            \Log::debug('PdfFirstPageOcr: full pdftotext failed', ['path' => $pdfPath, 'error' => $e->getMessage()]);

            return '';
        }
    }

    protected function makePdfToText(): Pdf
    {
        $binary = $this->binary('pdftotext', 'OCR_PDFTOTEXT_BINARY', 'pdftotext_binary');

        return $binary ? new Pdf($binary) : new Pdf();
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
     * Skips large files to avoid exhausting memory on Laravel Cloud.
     */
    protected function extractWithSmalot(string $pdfPath, ?int $maxPages = null): string
    {
        $size = is_file($pdfPath) ? (int) filesize($pdfPath) : 0;
        if ($size <= 0 || $size > self::SMALOT_MAX_BYTES) {
            \Log::debug('PdfFirstPageOcr: skipping smalot (file too large or missing)', [
                'path' => $pdfPath,
                'size' => $size,
            ]);

            return '';
        }

        $memoryLimit = ini_get('memory_limit');
        try {
            @ini_set('memory_limit', '512M');

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
        } finally {
            if (is_string($memoryLimit) && $memoryLimit !== '') {
                @ini_set('memory_limit', $memoryLimit);
            }
            gc_collect_cycles();
        }
    }

    protected function isUsableText(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        return mb_strlen(preg_replace('/\s+/', '', $trimmed) ?? '') >= 12;
    }

    protected function mergeText(string $layer, string $ocr): string
    {
        $layer = trim($layer);
        $ocr = trim($ocr);
        if ($layer === '') {
            return $ocr;
        }
        if ($ocr === '') {
            return $layer;
        }
        if (mb_stripos($ocr, $layer) !== false) {
            return $ocr;
        }

        return $layer."\n\n".$ocr;
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

    protected function renderPageToPng(string $pdfPath, string $tempDir, string $imagePath, int $page): ?string
    {
        $page = max(1, $page);

        $pdftoppm = $this->binary('pdftoppm', 'OCR_PDFTOPPM_BINARY', 'pdftoppm_binary');
        if ($pdftoppm) {
            $process = new Process([
                $pdftoppm, '-png', '-f', (string) $page, '-l', (string) $page, '-r', '200',
                $pdfPath, $imagePath,
            ]);
            $process->setTimeout(60);
            $process->run();
            if ($process->isSuccessful()) {
                foreach ([
                    $imagePath.'-'.$page.'.png',
                    $imagePath.'-'.sprintf('%02d', $page).'.png',
                ] as $png) {
                    if (is_file($png)) {
                        return $png;
                    }
                }
                $found = glob($tempDir.'/*.png');
                if (! empty($found[0])) {
                    return $found[0];
                }
            }
        }

        if (class_exists(\Imagick::class)) {
            try {
                $png = $tempDir.'/imagick-'.$page.'.png';
                $im = new \Imagick();
                $im->setResolution(160, 160);
                $im->readImage($pdfPath.'['.($page - 1).']');
                $im->setImageFormat('png');
                $im->writeImage($png);
                $im->clear();
                $im->destroy();
                if (is_file($png)) {
                    return $png;
                }
            } catch (\Throwable $e) {
                \Log::debug('PdfOcr: Imagick render failed', ['page' => $page, 'error' => $e->getMessage()]);
            }
        }

        foreach (['magick', 'convert'] as $magickCmd) {
            $bin = $this->findOnPath($magickCmd);
            if (! $bin) {
                continue;
            }
            $png = $tempDir.'/magick-'.$page.'.png';
            $process = new Process([$bin, $pdfPath.'['.($page - 1).']', '-density', '160', $png]);
            $process->setTimeout(60);
            $process->run();
            if ($process->isSuccessful() && is_file($png)) {
                return $png;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function extractEmbeddedJpegs(string $pdfPath, string $tempDir, int $max): array
    {
        $size = is_file($pdfPath) ? (int) filesize($pdfPath) : 0;
        if ($size <= 0 || $size > 40 * 1024 * 1024) {
            return [];
        }

        $binary = file_get_contents($pdfPath);
        if (! is_string($binary) || $binary === '') {
            return [];
        }

        $paths = [];
        $offset = 0;
        $length = strlen($binary);
        while (count($paths) < $max && $offset < $length) {
            $start = strpos($binary, "\xFF\xD8\xFF", $offset);
            if ($start === false) {
                break;
            }
            $end = strpos($binary, "\xFF\xD9", $start + 3);
            if ($end === false) {
                break;
            }
            $jpeg = substr($binary, $start, $end - $start + 2);
            $offset = $end + 2;
            if (strlen($jpeg) < 20000) {
                continue;
            }
            $path = $tempDir.'/embed-'.count($paths).'.jpg';
            file_put_contents($path, $jpeg);
            $paths[] = $path;
        }

        return $paths;
    }

    protected function tesseractBinary(): ?string
    {
        return $this->binary('tesseract', 'OCR_TESSERACT_BINARY', 'tesseract_binary', [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        ]);
    }

    protected function nodeBinary(): ?string
    {
        $configured = $this->binary('node', 'OCR_NODE_BINARY', 'node_binary', [
            base_path('bin/runtime-node'),
            base_path('bin/runtime-node.exe'),
        ]);
        if ($configured) {
            return $configured;
        }

        return $this->findOnPath('node') ?: $this->findOnPath('node.exe');
    }

    /**
     * @param  list<string>  $extraPaths
     */
    protected function binary(string $name, string $envKey, string $configKey, array $extraPaths = []): ?string
    {
        $configured = trim((string) (config('ocr.'.$configKey) ?: env($envKey, '')));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ($extraPaths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $this->findOnPath($name);
    }

    protected function findOnPath(string $name): ?string
    {
        $finder = new ExecutableFinder();

        return $finder->find($name);
    }
}
