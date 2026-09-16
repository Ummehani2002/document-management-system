<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Azure Document Intelligence / Computer Vision Read for scanned PDFs.
 */
class AzureDocumentIntelligenceService
{
    /** @var list<string> */
    protected array $lastErrors = [];

    public function enabled(): bool
    {
        if (! filter_var(config('services.azure_ai.ocr_enabled', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return filled(config('services.azure_ai.endpoint'))
            && filled(config('services.azure_ai.key'));
    }

    /**
     * @return list<string>
     */
    public function lastErrors(): array
    {
        return $this->lastErrors;
    }

    /**
     * Prefer a temporary storage URL for large cloud files; fall back to local bytes.
     */
    public function extractTextFromStorage(string $disk, string $path, string $mime = 'application/pdf'): string
    {
        $this->lastErrors = [];

        if (! $this->enabled()) {
            $this->lastErrors[] = 'Azure OCR not enabled.';

            return '';
        }

        if (! Storage::disk($disk)->exists($path)) {
            $this->lastErrors[] = 'Storage file missing: '.$path;

            return '';
        }

        $size = (int) Storage::disk($disk)->size($path);
        $maxBytes = (int) config('services.azure_ai.max_bytes', 100 * 1024 * 1024);
        if ($size <= 0 || $size > $maxBytes) {
            $msg = "Azure OCR skipped: file size {$size} exceeds max {$maxBytes}.";
            $this->lastErrors[] = $msg;
            Log::warning($msg, ['path' => $path]);

            return '';
        }

        // Large PDFs: use urlSource (base64 JSON balloons memory and often fails above ~20MB).
        $preferUrl = $size >= (int) config('services.azure_ai.url_min_bytes', 4 * 1024 * 1024);
        if ($preferUrl) {
            $url = $this->temporaryUrl($disk, $path);
            if ($url !== null) {
                $text = $this->analyzeViaUrl($url);
                if (trim($text) !== '') {
                    return $text;
                }
            } else {
                $this->rememberError('Could not create temporary URL for large file; trying local upload.');
            }
        }

        $binary = Storage::disk($disk)->get($path);
        if (! is_string($binary) || $binary === '') {
            $this->lastErrors[] = 'Could not read file bytes from storage.';

            return '';
        }

        return $this->extractTextFromBinary($binary, $mime);
    }

    /**
     * Extract plain text from a local PDF/image file.
     */
    public function extractTextFromFile(string $path, string $mime = 'application/pdf'): string
    {
        $this->lastErrors = [];

        if (! $this->enabled() || ! is_file($path)) {
            $this->lastErrors[] = 'Azure OCR not enabled or file missing.';

            return '';
        }

        $size = filesize($path) ?: 0;
        $maxBytes = (int) config('services.azure_ai.max_bytes', 100 * 1024 * 1024);
        if ($size <= 0 || $size > $maxBytes) {
            $msg = "Azure OCR skipped: file size {$size} exceeds max {$maxBytes}.";
            $this->lastErrors[] = $msg;
            Log::warning($msg, ['path' => $path]);

            return '';
        }

        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            $this->lastErrors[] = 'Could not read file bytes.';

            return '';
        }

        return $this->extractTextFromBinary($binary, $mime);
    }

    protected function extractTextFromBinary(string $binary, string $mime): string
    {
        $endpoint = rtrim((string) config('services.azure_ai.endpoint'), '/');
        $key = (string) config('services.azure_ai.key');

        $text = $this->analyzeDocumentIntelligenceBase64($endpoint, $key, $binary);
        if (trim($text) !== '') {
            return $text;
        }

        $text = $this->analyzeFormRecognizerBinary(
            $endpoint.'/formrecognizer/documentModels/prebuilt-read:analyze?api-version=2023-07-31',
            $key,
            $binary,
            $mime
        );
        if (trim($text) !== '') {
            return $text;
        }

        $text = $this->analyzeComputerVisionRead($endpoint, $key, $binary, $mime);
        if (trim($text) !== '') {
            return $text;
        }

        return '';
    }

    protected function temporaryUrl(string $disk, string $path): ?string
    {
        try {
            $adapter = Storage::disk($disk);
            if (! method_exists($adapter, 'temporaryUrl')) {
                return null;
            }

            return $adapter->temporaryUrl($path, now()->addMinutes(45));
        } catch (\Throwable $e) {
            $this->rememberError('temporaryUrl failed: '.$e->getMessage());

            return null;
        }
    }

    protected function analyzeViaUrl(string $fileUrl): string
    {
        $endpoint = rtrim((string) config('services.azure_ai.endpoint'), '/');
        $key = (string) config('services.azure_ai.key');
        $payload = ['urlSource' => $fileUrl];

        $apiVersions = [
            (string) config('services.azure_ai.api_version', '2024-11-30'),
            '2024-07-31-preview',
            '2023-07-31',
        ];

        foreach ($apiVersions as $version) {
            $urls = [
                $endpoint.'/documentintelligence/documentModels/prebuilt-read:analyze?api-version='.$version,
                $endpoint.'/formrecognizer/documentModels/prebuilt-read:analyze?api-version='.$version,
            ];

            foreach ($urls as $url) {
                try {
                    $text = $this->postJsonAnalyze($url, $key, $payload);
                    if (trim($text) !== '') {
                        return $text;
                    }
                } catch (\Throwable $e) {
                    $this->rememberError('DI urlSource '.$version.': '.$e->getMessage());
                }
            }
        }

        return '';
    }

    protected function analyzeDocumentIntelligenceBase64(string $endpoint, string $key, string $binary): string
    {
        // Avoid huge base64 payloads in memory (roughly 4/3 of file size).
        if (strlen($binary) > 15 * 1024 * 1024) {
            $this->rememberError('Skipping base64 upload for file > 15MB; use urlSource.');

            return '';
        }

        $apiVersions = [
            (string) config('services.azure_ai.api_version', '2024-11-30'),
            '2024-07-31-preview',
            '2023-07-31',
        ];

        $payload = ['base64Source' => base64_encode($binary)];

        foreach ($apiVersions as $version) {
            $urls = [
                $endpoint.'/documentintelligence/documentModels/prebuilt-read:analyze?api-version='.$version,
                $endpoint.'/formrecognizer/documentModels/prebuilt-read:analyze?api-version='.$version,
            ];

            foreach ($urls as $url) {
                try {
                    $text = $this->postJsonAnalyze($url, $key, $payload);
                    if (trim($text) !== '') {
                        return $text;
                    }
                } catch (\Throwable $e) {
                    $this->rememberError('DI '.$version.': '.$e->getMessage());
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postJsonAnalyze(string $url, string $key, array $payload): string
    {
        $start = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $key,
            'Content-Type' => 'application/json',
        ])
            ->timeout(180)
            ->post($url, $payload);

        return $this->handleAnalyzeResponse($start, $key, $url);
    }

    protected function analyzeFormRecognizerBinary(string $url, string $key, string $binary, string $mime): string
    {
        if (strlen($binary) > 20 * 1024 * 1024) {
            $this->rememberError('Skipping FormRecognizer binary upload for file > 20MB.');

            return '';
        }

        try {
            $start = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
                'Content-Type' => $mime,
            ])
                ->timeout(180)
                ->withBody($binary, $mime)
                ->post($url);

            return $this->handleAnalyzeResponse($start, $key, $url);
        } catch (\Throwable $e) {
            $this->rememberError('FormRecognizer binary: '.$e->getMessage());

            return '';
        }
    }

    protected function analyzeComputerVisionRead(string $endpoint, string $key, string $binary, string $mime): string
    {
        if (strlen($binary) > 20 * 1024 * 1024) {
            $this->rememberError('Skipping Vision binary upload for file > 20MB.');

            return '';
        }

        $urls = [
            $endpoint.'/vision/v3.2/read/analyze',
            $endpoint.'/computervision/imageanalysis:analyze?api-version=2023-02-01-preview&features=read',
        ];

        foreach ($urls as $url) {
            try {
                $start = Http::withHeaders([
                    'Ocp-Apim-Subscription-Key' => $key,
                    'Content-Type' => $mime,
                ])
                    ->timeout(180)
                    ->withBody($binary, $mime)
                    ->post($url);

                if ($start->status() === 202) {
                    $operation = $start->header('Operation-Location') ?: $start->header('operation-location');
                    if (! $operation) {
                        $this->rememberError('Vision Read: 202 without Operation-Location ('.$url.')');

                        continue;
                    }

                    return $this->pollVisionReadResult($operation, $key);
                }

                if ($start->successful()) {
                    $text = $this->contentFromPayload($start->json() ?? []);
                    if ($text !== '') {
                        return $text;
                    }
                }

                $this->rememberError('Vision '.$start->status().' '.$url.': '.mb_substr($start->body(), 0, 300));
            } catch (\Throwable $e) {
                $this->rememberError('Vision: '.$e->getMessage());
            }
        }

        return '';
    }

    protected function handleAnalyzeResponse(\Illuminate\Http\Client\Response $start, string $key, string $url): string
    {
        if ($start->status() === 202) {
            $operation = $start->header('Operation-Location') ?: $start->header('operation-location');
            if (! $operation) {
                $this->rememberError('202 without Operation-Location: '.$url);

                return '';
            }

            return $this->pollResult($operation, $key);
        }

        if (! $start->successful()) {
            $this->rememberError($start->status().' '.$url.': '.mb_substr($start->body(), 0, 400));
            Log::warning('Azure OCR analyze rejected', [
                'status' => $start->status(),
                'url' => $url,
                'body' => mb_substr($start->body(), 0, 500),
            ]);

            return '';
        }

        return $this->contentFromPayload($start->json() ?? []);
    }

    protected function pollResult(string $operationUrl, string $key): string
    {
        $attempts = 0;
        while ($attempts < 90) {
            $attempts++;
            usleep(700000);

            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
            ])
                ->timeout(60)
                ->get($operationUrl);

            if (! $response->successful()) {
                continue;
            }

            $payload = $response->json() ?? [];
            $status = strtolower((string) data_get($payload, 'status', ''));

            if (in_array($status, ['succeeded', 'success'], true)) {
                return $this->contentFromPayload($payload);
            }

            if (in_array($status, ['failed', 'canceled', 'cancelled'], true)) {
                $this->rememberError('Operation failed: '.mb_substr(json_encode($payload) ?: '', 0, 400));
                Log::warning('Azure OCR operation failed', ['payload' => $payload]);

                return '';
            }
        }

        $this->rememberError('Operation timed out: '.$operationUrl);
        Log::warning('Azure OCR operation timed out', ['url' => $operationUrl]);

        return '';
    }

    protected function pollVisionReadResult(string $operationUrl, string $key): string
    {
        $attempts = 0;
        while ($attempts < 90) {
            $attempts++;
            usleep(700000);

            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
            ])
                ->timeout(60)
                ->get($operationUrl);

            if (! $response->successful()) {
                continue;
            }

            $payload = $response->json() ?? [];
            $status = strtolower((string) data_get($payload, 'status', ''));

            if ($status === 'succeeded') {
                $lines = [];
                foreach (data_get($payload, 'analyzeResult.readResults', []) as $page) {
                    foreach (($page['lines'] ?? []) as $line) {
                        if (! empty($line['text'])) {
                            $lines[] = (string) $line['text'];
                        }
                    }
                }

                return trim(implode("\n", $lines));
            }

            if (in_array($status, ['failed', 'canceled', 'cancelled'], true)) {
                $this->rememberError('Vision operation failed');

                return '';
            }
        }

        $this->rememberError('Vision operation timed out');

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function contentFromPayload(array $payload): string
    {
        $content = data_get($payload, 'analyzeResult.content')
            ?? data_get($payload, 'content')
            ?? data_get($payload, 'readResult.content');

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        $lines = [];
        $pages = data_get($payload, 'analyzeResult.pages', []);
        if (is_array($pages)) {
            foreach ($pages as $page) {
                foreach (($page['lines'] ?? []) as $line) {
                    if (! empty($line['content'])) {
                        $lines[] = (string) $line['content'];
                    } elseif (! empty($line['text'])) {
                        $lines[] = (string) $line['text'];
                    }
                }
            }
        }

        foreach (data_get($payload, 'readResult.blocks', []) as $block) {
            foreach (($block['lines'] ?? []) as $line) {
                if (! empty($line['text'])) {
                    $lines[] = (string) $line['text'];
                }
            }
        }

        return trim(implode("\n", $lines));
    }

    protected function rememberError(string $message): void
    {
        $this->lastErrors[] = $message;
        Log::warning('Azure OCR: '.$message);
    }
}
