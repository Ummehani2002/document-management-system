<?php

namespace App\Services;

use App\Models\Document;

/**
 * Microsoft-DMS style document properties: persist Reference + Subject on upload.
 */
class DocumentMetaService
{
    /**
     * @return array{reference_no:string, subject:string}
     */
    public static function displayFor(Document $document): array
    {
        $storedRef = self::normalizeStored((string) ($document->reference_no ?? ''));
        $storedSubject = self::normalizeStored((string) ($document->subject ?? ''));
        $dirty = false;

        $subjectNeedsRepair = $storedSubject !== null && DocumentFilenameParser::isUnusableSubject($storedSubject);
        $parsed = null;
        if ($subjectNeedsRepair || $storedRef !== null) {
            $parsed = DocumentFilenameParser::extractReferenceAndSubject(
                $document->ocr_text,
                (string) $document->file_name
            );
        }

        if ($subjectNeedsRepair) {
            $repaired = self::normalizeStored((string) ($parsed['subject'] ?? ''));
            if ($repaired !== null && ! DocumentFilenameParser::isUnusableSubject($repaired)) {
                $document->subject = mb_substr($repaired, 0, 1000);
                $storedSubject = $repaired;
                $dirty = true;
            }
        }

        if ($storedRef !== null) {
            $parsedRef = self::normalizeStored((string) ($parsed['reference_no'] ?? ''));
            if ($parsedRef !== null && DocumentFilenameParser::referenceExtends($storedRef, $parsedRef)) {
                $document->reference_no = mb_substr($parsedRef, 0, 255);
                $storedRef = $parsedRef;
                $dirty = true;
            }
        }

        if ($dirty && $document->exists) {
            $document->save();
        }

        if ($storedRef !== null && $storedSubject !== null) {
            return [
                'reference_no' => $storedRef,
                'subject' => $storedSubject,
            ];
        }

        $parsed = DocumentFilenameParser::extractReferenceAndSubject(
            $document->ocr_text,
            (string) $document->file_name
        );

        return [
            'reference_no' => $storedRef ?? ($parsed['reference_no'] ?? '—'),
            'subject' => $storedSubject ?? ($parsed['subject'] ?? '—'),
        ];
    }

    /**
     * Parse OCR/filename and save Reference + Subject onto the document.
     *
     * @param  array{reference_no?:string, subject?:string}|null  $preferred
     * @return array{reference_no:string, subject:string}
     */
    public function syncFromText(
        Document $document,
        ?string $ocrText = null,
        ?array $preferred = null,
        string $source = 'ocr'
    ): array {
        $text = $ocrText !== null ? $ocrText : $document->ocr_text;
        $parsed = DocumentFilenameParser::extractReferenceAndSubject(
            $text,
            (string) $document->file_name
        );

        $reference = $this->pickValue(
            $preferred['reference_no'] ?? null,
            $parsed['reference_no'] ?? null,
            $document->reference_no
        );
        $subject = $this->pickValue(
            $preferred['subject'] ?? null,
            $parsed['subject'] ?? null,
            $document->subject
        );

        $document->reference_no = $reference === '—' ? null : mb_substr($reference, 0, 255);
        $document->subject = $subject === '—' ? null : mb_substr($subject, 0, 1000);
        $document->meta_source = $source;
        if ($ocrText !== null) {
            $normalized = trim((string) $ocrText);
            $document->ocr_text = $normalized !== '' ? $normalized : $document->ocr_text;
        }
        $document->save();

        return [
            'reference_no' => $reference,
            'subject' => $subject,
        ];
    }

    /**
     * Apply AI vision result (preferred) then fill gaps from OCR/filename parse.
     *
     * @param  array{reference_no?:string, subject?:string, ocr_text?:string, folder?:string}  $ai
     * @return array{reference_no:string, subject:string, folder:?string}
     */
    public function syncFromAiResult(Document $document, array $ai): array
    {
        $ocrText = isset($ai['ocr_text']) ? trim((string) $ai['ocr_text']) : null;
        if ($ocrText !== null && $ocrText !== '') {
            $existing = trim((string) ($document->ocr_text ?? ''));
            if ($existing === '' || mb_strlen($ocrText) >= 20) {
                $document->ocr_text = $ocrText;
            }
        }

        $meta = $this->syncFromText($document, $document->ocr_text, [
            'reference_no' => $ai['reference_no'] ?? null,
            'subject' => $ai['subject'] ?? null,
        ], 'ai');

        $folder = trim((string) ($ai['folder'] ?? ''));

        return [
            'reference_no' => $meta['reference_no'],
            'subject' => $meta['subject'],
            'folder' => $folder !== '' && $folder !== '—' ? $folder : null,
        ];
    }

    protected function pickValue(?string $preferred, ?string $parsed, mixed $existing): string
    {
        foreach ([$preferred, $parsed, $existing] as $value) {
            $normalized = self::normalizeStored((string) ($value ?? ''));
            if ($normalized !== null && ! DocumentFilenameParser::isUnusableSubject($normalized)) {
                return $normalized;
            }
        }

        return '—';
    }

    protected static function normalizeStored(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '—') {
            return null;
        }

        return $value;
    }
}
