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

        try {
            self::repairStoredFolder($document);
            [$storedRef, $storedSubject] = self::repairStoredMeta($document, $storedRef, $storedSubject);
        } catch (\Throwable $e) {
            self::reportQuietly($e);
        }

        $revision = self::rememberRevision($document, $storedRef);

        if ($storedRef !== null && $storedSubject !== null) {
            return [
                'reference_no' => $storedRef,
                'revision_no' => $revision,
                'subject' => $storedSubject,
            ];
        }

        try {
            $parsed = DocumentFilenameParser::extractReferenceAndSubject(
                $document->ocr_text,
                (string) $document->file_name
            );
        } catch (\Throwable $e) {
            self::reportQuietly($e);
            $parsed = ['reference_no' => '—', 'subject' => '—'];
        }

        $reference = $storedRef ?? ($parsed['reference_no'] ?? '—');

        return [
            'reference_no' => $reference,
            'revision_no' => self::rememberRevision($document, $reference === '—' ? null : $reference),
            'subject' => $storedSubject ?? ($parsed['subject'] ?? '—'),
        ];
    }

    /**
     * Keep the folder named by the document number (EMI, LTR, SDW, ABD).
     * The file on disk is left where it is; reclassification moves it later.
     */
    private static function repairStoredFolder(Document $document): void
    {
        if ($document->folder_locked || ! $document->exists) {
            return;
        }

        $folder = DocumentFilenameParser::folderFromFilenameRegisterCode((string) $document->file_name);
        if ($folder === null || $folder === (string) $document->document_type) {
            return;
        }

        try {
            $document->document_type = $folder;
            $document->save();
        } catch (\Throwable $e) {
            self::reportQuietly($e);
        }
    }

    /**
     * Fix a bad stored subject, and lengthen a short reference from the filename.
     * Full OCR is read only when the stored subject is unusable, so a normal
     * search row does not re-parse the document text.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function repairStoredMeta(Document $document, ?string $storedRef, ?string $storedSubject): array
    {
        $dirty = false;
        if ($storedSubject !== null) {
            $trimmedSubject = self::normalizeStored(DocumentFilenameParser::cleanDisplayedSubject(
                DocumentFilenameParser::stripTrailingFormLabels($storedSubject),
                (string) $document->file_name
            ));
            if ($trimmedSubject !== $storedSubject) {
                if ($trimmedSubject !== null && ! DocumentFilenameParser::isUnusableSubject($trimmedSubject)) {
                    $document->subject = mb_substr($trimmedSubject, 0, 1000);
                    $storedSubject = $trimmedSubject;
                } else {
                    $document->subject = null;
                    $storedSubject = null;
                }
                $dirty = true;
            }
        }

        $subjectNeedsRepair = $storedSubject !== null && DocumentFilenameParser::isUnusableSubject($storedSubject);

        if ($storedRef !== null) {
            $fromName = DocumentFilenameParser::extractReferenceAndSubject(null, (string) $document->file_name);
            $filenameRef = self::normalizeStored((string) ($fromName['reference_no'] ?? ''));
            $registerFolder = DocumentFilenameParser::folderFromFilenameRegisterCode((string) $document->file_name);
            if ($filenameRef !== null && DocumentFilenameParser::referenceExtends($storedRef, $filenameRef)) {
                $document->reference_no = mb_substr($filenameRef, 0, 255);
                $storedRef = $filenameRef;
                $dirty = true;
            } elseif ($registerFolder !== null
                && $filenameRef !== null
                && ! DocumentFilenameParser::referencesAreSameDocument($storedRef, $filenameRef)) {
                $document->reference_no = mb_substr($filenameRef, 0, 255);
                $storedRef = $filenameRef;
                $dirty = true;
            }
        }

        if ($subjectNeedsRepair) {
            $parsed = DocumentFilenameParser::extractReferenceAndSubject(
                $document->ocr_text,
                (string) $document->file_name
            );
            $repaired = self::normalizeStored((string) ($parsed['subject'] ?? ''));
            if ($repaired !== null && ! DocumentFilenameParser::isUnusableSubject($repaired)) {
                $document->subject = mb_substr($repaired, 0, 1000);
                $storedSubject = $repaired;
                $dirty = true;
            }

            $parsedRef = self::normalizeStored((string) ($parsed['reference_no'] ?? ''));
            if ($storedRef !== null && $parsedRef !== null && DocumentFilenameParser::referenceExtends($storedRef, $parsedRef)) {
                $document->reference_no = mb_substr($parsedRef, 0, 255);
                $storedRef = $parsedRef;
                $dirty = true;
            }
        }

        if ($storedRef !== null && preg_match('/^(.*)-\((\d{2})\)$/', $storedRef, $m) && $m[1] !== '') {
            $storedRef = $m[1];
            $document->reference_no = mb_substr($storedRef, 0, 255);
            $dirty = true;
        }

        if ($dirty && $document->exists) {
            try {
                $document->save();
            } catch (\Throwable $e) {
                self::reportQuietly($e);
            }
        }

        return [$storedRef, $storedSubject];
    }

    private static function rememberRevision(Document $document, ?string $reference): string
    {
        $detected = DocumentFilenameParser::revisionNumber(
            $document->ocr_text,
            (string) $document->file_name,
            (string) ($reference ?? '')
        );
        $stored = self::normalizeStored((string) ($document->revision_no ?? ''));
        if ($detected !== null && $detected !== $stored && $document->exists) {
            try {
                $document->revision_no = $detected;
                $document->save();
            } catch (\Throwable $e) {
                self::reportQuietly($e);
            }
        }

        return $detected ?? $stored ?? '—';
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

        if (preg_match('/^(.*)-\((\d{2})\)$/', $reference, $m) && $m[1] !== '') {
            $reference = $m[1];
        }
        $revision = DocumentFilenameParser::revisionNumber(
            $text,
            (string) $document->file_name,
            $reference
        );

        $document->reference_no = $reference === '—' ? null : mb_substr($reference, 0, 255);
        if ($revision !== null) {
            $document->revision_no = $revision;
        }
        $document->subject = $subject === '—' ? null : mb_substr($subject, 0, 1000);
        $document->meta_source = $source;
        if ($ocrText !== null) {
            $normalized = trim((string) $ocrText);
            $document->ocr_text = $normalized !== '' ? $normalized : $document->ocr_text;
        }
        $document->save();

        return [
            'reference_no' => $reference,
            'revision_no' => $revision ?? (self::normalizeStored((string) ($document->revision_no ?? '')) ?? '—'),
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

    private static function reportQuietly(\Throwable $e): void
    {
        try {
            report($e);
        } catch (\Throwable) {
        }
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
