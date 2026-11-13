<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vision LLM extraction of Reference No + Subject (Description/Title) from page-1 form images.
 * Used when Tesseract misses italic filled values on scanned PDFs.
 */
class FormFieldVisionExtractor
{
    public function enabled(): bool
    {
        $key = trim((string) config('services.openai.api_key', ''));

        return $key !== '';
    }

    /**
     * @return array{reference_no:string, subject:string, ocr_text:string}|null
     */
    public function extractFromImageBase64(string $base64, string $mime = 'image/jpeg'): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $base64 = preg_replace('/^data:[^;]+;base64,/', '', trim($base64)) ?? trim($base64);
        if ($base64 === '' || strlen($base64) < 100) {
            return null;
        }

        $mime = strtolower(trim($mime));
        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            $mime = 'image/jpeg';
        }

        $model = (string) config('services.openai.vision_model', 'gpt-4o-mini');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $apiKey = (string) config('services.openai.api_key');

        $prompt = <<<'PROMPT'
You are reading page 1 of a construction document form (Inspection Request, Work Inspection, Material Submittal, MIR, RFI, Method Statement, Shop Drawing, Internal Memo, etc.).

Return ONLY valid JSON with these keys:
{
  "reference_no": "IR Ref No / Submittal No / Ref No / MIR No from the form (not drawing ref unless that is the only ref). Empty string if none.",
  "subject": "The filled Description / Title / Subject text from the form body ONLY."
}

Rules for subject:
- Prefer: "Description of Works to be inspected and /or tested", "Title / Subject", "Title & Description", "Material Description", "Drawing Title", "Method Statement Title", or "Subject:".
- For Inspection Request forms, subject MUST be the long filled line under Description of Works (example: "Inspection request for Excavation of Palm tree pits - 08 Nos. as shown in the attached drawing and site pictures.").
- Do NOT use revision/status filename text such as "R.00 (B)_Approved as noted".
- Do NOT use project name, client, contractor, location-only cells, or folder type names as subject.
- Keep subject to one clean sentence/paragraph; no trailing Location/Drawing Ref/Date of Inspection fields.
PROMPT;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => 'data:'.$mime.';base64,'.$base64,
                                        'detail' => 'high',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('FormFieldVisionExtractor API failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $parsed = json_decode($content, true);
            if (! is_array($parsed)) {
                return null;
            }

            $reference = trim((string) ($parsed['reference_no'] ?? ''));
            $subject = trim((string) ($parsed['subject'] ?? ''));
            $subject = preg_replace('/\s+/', ' ', $subject) ?? $subject;
            $subject = trim($subject);

            if ($subject !== '' && preg_match('/Approved\s+as\s+noted|^R\.?\d+(?:\s*\([A-Z]\))?/i', $subject)) {
                $subject = '';
            }

            if ($subject === '' && $reference === '') {
                return null;
            }

            $ocrText = $this->toOcrText($reference, $subject);

            return [
                'reference_no' => $reference !== '' ? $reference : '—',
                'subject' => $subject !== '' ? $subject : '—',
                'ocr_text' => $ocrText,
            ];
        } catch (\Throwable $e) {
            Log::warning('FormFieldVisionExtractor exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function toOcrText(string $reference, string $subject): string
    {
        $lines = ['DOCUMENT FORM PAGE 1'];
        if ($reference !== '') {
            $lines[] = 'IR Ref No. '.$reference;
            $lines[] = 'Ref. No: '.$reference;
            $lines[] = 'Submittal No: '.$reference;
        }
        if ($subject !== '') {
            $lines[] = 'Title / Subject';
            $lines[] = $subject;
            $lines[] = 'Description of Works to be inspected and /or tested:';
            $lines[] = $subject;
            $lines[] = 'Material Description:';
            $lines[] = $subject;
        }

        return implode("\n", $lines);
    }
}
