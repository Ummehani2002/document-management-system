<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure Document Intelligence (prebuilt-read) for scanned / image-only PDFs.
 */
class AzureDocumentIntelligenceService
{
    public function enabled(): bool
    {
        if (! filter_var(config('services.azure_ai.ocr_enabled', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return filled(config('services.azure_ai.endpoint'))
            && filled(config('services.azure_ai.key'));
    }

    /**
     * Extract plain text from a local PDF/image file.
     */
    public function extractTextFromFile(string $path, string $mime = 'application/pdf'): string
    {
        if (! $this->enabled() || ! is_file($path)) {
            return '';
        }

        $size = filesize($path) ?: 0;
        $maxBytes = (int) config('services.azure_ai.max_bytes', 20 * 1024 * 1024);
        if ($size <= 0 || $size > $maxBytes) {
            Log::warning('Azure OCR skipped: file missing or too large', [
                'path' => $path,
                'size' => $size,
                'max' => $maxBytes,
            ]);

            return '';
        }

        $endpoint = rtrim((string) config('services.azure_ai.endpoint'), '/');
        $key = (string) config('services.azure_ai.key');
        $apiVersion = (string) config('services.azure_ai.api_version', '2024-11-30');

        $urls = [
            $endpoint.'/documentintelligence/documentModels/prebuilt-read:analyze?api-version='.$apiVersion,
            $endpoint.'/formrecognizer/documentModels/prebuilt-read:analyze?api-version=2023-07-31',
        ];

        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            return '';
        }

        foreach ($urls as $analyzeUrl) {
            try {
                $text = $this->analyzeBinary($analyzeUrl, $key, $binary, $mime);
                if (trim($text) !== '') {
                    return $text;
                }
            } catch (\Throwable $e) {
                Log::warning('Azure OCR attempt failed', [
                    'url' => $analyzeUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return '';
    }

    protected function analyzeBinary(string $analyzeUrl, string $key, string $binary, string $mime): string
    {
        $start = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $key,
            'Content-Type' => $mime,
        ])
            ->timeout(120)
            ->withBody($binary, $mime)
            ->post($analyzeUrl);

        if ($start->status() === 202) {
            $operation = $start->header('Operation-Location') ?: $start->header('operation-location');
            if (! $operation) {
                return '';
            }

            return $this->pollResult($operation, $key);
        }

        if (! $start->successful()) {
            Log::warning('Azure OCR analyze rejected', [
                'status' => $start->status(),
                'body' => mb_substr($start->body(), 0, 500),
            ]);

            return '';
        }

        return $this->contentFromPayload($start->json());
    }

    protected function pollResult(string $operationUrl, string $key): string
    {
        $attempts = 0;
        while ($attempts < 60) {
            $attempts++;
            usleep(500000);

            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
            ])
                ->timeout(60)
                ->get($operationUrl);

            if (! $response->successful()) {
                continue;
            }

            $payload = $response->json();
            $status = strtolower((string) data_get($payload, 'status', ''));

            if (in_array($status, ['succeeded', 'success'], true)) {
                return $this->contentFromPayload($payload);
            }

            if (in_array($status, ['failed', 'canceled', 'cancelled'], true)) {
                Log::warning('Azure OCR operation failed', ['payload' => $payload]);

                return '';
            }
        }

        Log::warning('Azure OCR operation timed out', ['url' => $operationUrl]);

        return '';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function contentFromPayload(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $content = data_get($payload, 'analyzeResult.content')
            ?? data_get($payload, 'analyzeResult.readResults')
            ?? data_get($payload, 'content');

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        // Older Read API style: pages -> lines -> text
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

        return trim(implode("\n", $lines));
    }
}
