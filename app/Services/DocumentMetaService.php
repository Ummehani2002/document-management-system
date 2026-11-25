<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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
            self::rereadEmployerInstructionPage($document);
            $storedRef = self::normalizeStored((string) ($document->reference_no ?? ''));
            $storedSubject = self::normalizeStored((string) ($document->subject ?? ''));
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
     * The saved text can be an earlier read that kept the mail number.
     * Open the PDF once and keep the first page when it has the reference-number field.
     */
    private static function rereadEmployerInstructionPage(Document $document): void
    {
        if ($document->folder_locked || ! $document->exists) {
            return;
        }
        if (! preg_match('/\.pdf$/i', (string) $document->file_name)) {
            return;
        }
        if (self::$employerPageRereads >= 4) {
            return;
        }

        $ocr = (string) $document->ocr_text;
        if (preg_match('/REFERENCE\s+NUMBER/i', $ocr)) {
            return;
        }

        $ref = (string) $document->reference_no;
        $isMailNumber = (bool) preg_match('/-EMPI-/i', $ref);
        $mentionsMail = (bool) preg_match('/MAIL\s+(?:TYPE|NUMBER)/i', $ocr);
        $filedAsInspection = strcasecmp((string) $document->document_type, 'Work Inspection') === 0
            && (bool) preg_match('/\b(?:EMI|EI)\b|INSTRUCTION/i', (string) $document->file_name);
        $badReference = (bool) preg_match('/(?:^|[-_])(?:OPERATION|HARDSCAPE|SOFTSCAPE|MANUAL|MAINTENANCE|PACKAGE|SIGNAGE|PREQUALIFICATION)(?:$|[-_])|aconex|\.html?$|^https?:/i', $ref);
        $subjectBlank = trim((string) $document->subject) === '';
        if (! $isMailNumber && ! $mentionsMail && ! $filedAsInspection && ! $badReference && ! $subjectBlank) {
            return;
        }

        $cacheKey = 'dms-ei-reread:'.$document->id.':'.substr(sha1($ocr), 0, 12);
        if (! Cache::add($cacheKey, 1, now()->addHours(12))) {
            return;
        }

        $location = DocumentLocationResolver::resolve((string) $document->file_path);
        if ($location === null) {
            Cache::forget($cacheKey);

            return;
        }

        self::$employerPageRereads++;
        $tempPath = null;
        try {
            if ($location['source'] === 'file') {
                $pdfPath = $location['path'];
            } else {
                $tempPath = tempnam(sys_get_temp_dir(), 'dms_ei_').'.pdf';
                file_put_contents($tempPath, Storage::disk($location['disk'])->get($location['path']));
                $pdfPath = $tempPath;
            }

            $useful = '/REFERENCE\s+NUMBER|MAIL\s+TYPE|EMPLOYER\'?S?\s+INSTRUCTION|DOC(?:UMENT)?\s*(?:NO\.?|NUMBER)|OPERATION\s+AND\s+MAINTENANCE|PRE[\s\-]*QUALIF/i';
            $reader = app(PdfFirstPageOcrService::class);
            $fresh = trim($reader->extractTextForClassification($pdfPath));
            if (! preg_match($useful, $fresh)) {
                $alt = trim($reader->extractFirstPageText($pdfPath));
                if (preg_match($useful, $alt)) {
                    $fresh = $alt;
                }
            }
            if (! preg_match($useful, $fresh)) {
                return;
            }

            $document->ocr_text = mb_substr($fresh, 0, 50000);
            $document->save();
        } catch (\Throwable $e) {
            self::reportQuietly($e);
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private static int $employerPageRereads = 0;

    /**
     * Settle the folder from the first-page text. The filename is not used.
     */
    private static function repairStoredFolder(Document $document): void
    {
        if ($document->folder_locked || ! $document->exists) {
            return;
        }

        $ocr = trim((string) $document->ocr_text);
        if ($ocr === '') {
            return;
        }

        $folder = (string) (DocumentFilenameParser::classifyForAutomation('', $ocr)['document_category'] ?? 'Other');
        if ($folder === '' || $folder === 'Other' || $folder === (string) $document->document_type) {
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

        $pageText = trim((string) $document->ocr_text);
        if ($pageText !== '') {
            $fromPage = DocumentFilenameParser::extractReferenceAndSubject($pageText, '');
            $pageRef = self::normalizeStored((string) ($fromPage['reference_no'] ?? ''));
            $pageSubject = self::normalizeStored((string) ($fromPage['subject'] ?? ''));
            if ($pageRef !== null && $pageRef !== $storedRef) {
                $document->reference_no = mb_substr($pageRef, 0, 255);
                $storedRef = $pageRef;
                $dirty = true;
            }
            if ($pageSubject !== null
                && ! DocumentFilenameParser::isUnusableSubject($pageSubject)
                && $pageSubject !== $storedSubject) {
                $document->subject = mb_substr($pageSubject, 0, 1000);
                $storedSubject = $pageSubject;
                $dirty = true;
            }
        }

        if ($subjectNeedsRepair) {
            $parsed = DocumentFilenameParser::extractReferenceAndSubject(
                $document->ocr_text,
                ''
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
        $parsed = DocumentFilenameParser::extractReferenceAndSubject($text, '');

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
