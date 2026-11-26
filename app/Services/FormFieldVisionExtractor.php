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
     * @return array{folder:string, reference_no:string, subject:string, ocr_text:string}|null
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
        $folderChoices = implode(', ', DocumentFilenameParser::allDocumentTypes());

        $prompt = <<<PROMPT
You are reading page 1 of a construction document form for a Document Management System.

Return ONLY valid JSON with these keys:
{
  "folder": "one folder name from this exact list: {$folderChoices}",
  "reference_no": "The value labeled REFERENCE NUMBER. If that label is missing, use IR Ref No / Submittal No / Ref No / MIR No. Never use MAIL NUMBER when REFERENCE NUMBER is present.",
  "subject": "The filled Description / Title / Subject text from the form body ONLY."
}

Rules for folder (choose exactly one from the list):
- A correspondence letter has DATE, REFERENCE, FROM, TO and SUBJECT, and usually opens with "Dear Sir". File it as Incoming Or Outgoing Letter. A reference like UNEC/33408341/PROSCAPE/2025/0072 is a letter number.
- Do not choose Material Submittal because the subject or the letter talks about material submittals. Material Submittal is only the submittal form itself (Material Title, Type of Submittal).
- Do not choose Engineers Instruction because the company name contains Engineering or the signatory is "Eng.". Engineers Instruction is only when the document title or MAIL TYPE is an Employer's Instruction or Engineer's Instruction.
- MAIL TYPE "Employers Instruction" or "Employer's Instruction", or a title that starts with Employer's Instruction / Engineers Instruction → Engineers Instruction. The word Instruction by itself is not a work inspection.
- Use Work Inspection only when the form title is WORK INSPECTION REQUEST or INSPECTION REQUEST.
- MATERIAL INSPECTION REQUEST / MIR → Material Inspection Request
- MATERIAL SUBMITTAL / MAT → Material Submittal
- METHOD STATEMENT → Method Statement
- SHOP DRAWING → Shop Drawing
- RFI / Request for Information → Request For Information
- INTERNAL MEMO → Internal Memo
- If unsure, pick the closest list value.

Rules for reference_no:
- On an Aconex mail, MAIL NUMBER and REFERENCE NUMBER are different. Example: MAIL NUMBER AMA02012-EMPI-000086 and REFERENCE NUMBER AMA02012-WTRAN-180892 means reference_no is AMA02012-WTRAN-180892.
- Do not return the mail number when a Reference Number is printed.

Rules for subject:
- On an Employer's Instruction, subject is the full bold title under the mail header, starting with Employer's Instruction. Keep the words after "Subject" as part of that title. Do not shorten it to only "Instruction to Commence…".
- Prefer, in this order: "Material Title", "Materials for Inspection", "Description of Materials for Inspection", "Description of Works to be inspected and /or tested", "Title / Subject", "Title & Description", "Material Description", "Drawing Title", "Method Statement Title", or "Subject:".
- For MATERIAL INSPECTION REQUEST forms, subject is the filled item name in "Materials for Inspection" or "Description of Materials for Inspection" (example: "Picnic table Round"). Do NOT use the photo column, "We confirm that the materials for inspection have been previously approved…", supplier, test report, or delivery note.
- For MATERIAL SUBMITTAL forms, subject is the "Material Title" line (example: "Material Submittal for Isolator for Swimming Pool and Water Features"). Do NOT use a later checklist row such as "Material Submittal for PVC Conduits… Yes No N/A Remark".
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
            $folder = trim((string) ($parsed['folder'] ?? ''));
            $folder = $this->normalizeFolder($folder);

            if ($subject !== '' && preg_match('/Approved\s+as\s+noted|^R\.?\d+(?:\s*\([A-Z]\))?/i', $subject)) {
                $subject = '';
            }

            if ($subject === '' && $reference === '' && $folder === '') {
                return null;
            }

            $ocrText = $this->toOcrText($reference, $subject, $folder);

            return [
                'folder' => $folder !== '' ? $folder : '—',
                'reference_no' => $reference !== '' ? $reference : '—',
                'subject' => $subject !== '' ? $subject : '—',
                'ocr_text' => $ocrText,
            ];
        } catch (\Throwable $e) {
            Log::warning('FormFieldVisionExtractor exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function normalizeFolder(string $folder): string
    {
        $folder = trim($folder);
        if ($folder === '') {
            return '';
        }

        foreach (DocumentFilenameParser::allDocumentTypes() as $allowed) {
            if (strcasecmp($allowed, $folder) === 0) {
                return $allowed;
            }
        }

        return '';
    }

    protected function toOcrText(string $reference, string $subject, string $folder = ''): string
    {
        if (strcasecmp($folder, 'Engineers Instruction') === 0) {
            $lines = [
                'MAIL TYPE',
                'Employers Instruction',
                "Employer's Instruction",
            ];
            if ($reference !== '') {
                $lines[] = 'REFERENCE NUMBER';
                $lines[] = $reference;
            }
            if ($subject !== '') {
                $lines[] = $subject;
            }

            return implode("\n", $lines);
        }

        if (strcasecmp($folder, 'Incoming Or Outgoing Letter') === 0) {
            $lines = ['CORRESPONDENCE LETTER'];
            if ($reference !== '') {
                $lines[] = 'REFERENCE';
                $lines[] = $reference;
                $lines[] = 'Ref. No: '.$reference;
            }
            if ($subject !== '') {
                $lines[] = 'SUBJECT';
                $lines[] = $subject;
            }
            $lines[] = 'FROM';
            $lines[] = 'TO';
            $lines[] = 'Dear Sir,';

            return implode("\n", $lines);
        }

        $lines = ['DOCUMENT FORM PAGE 1'];
        if ($folder !== '') {
            $lines[] = strtoupper($folder);
        }
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
