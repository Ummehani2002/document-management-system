<?php

namespace App\Services;

use App\Models\Document;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Auth;

class UserActivityLogger
{
    public static function uploaded(Document $document, array $extra = []): void
    {
        self::log(UserActivity::ACTION_UPLOADED, $document, $extra);
    }

    public static function reattached(Document $document, array $extra = []): void
    {
        self::log(UserActivity::ACTION_REATTACHED, $document, $extra);
    }

    public static function replaced(Document $document, array $extra = []): void
    {
        self::log(UserActivity::ACTION_REPLACED, $document, $extra);
    }

    public static function deleted(Document $document, array $extra = []): void
    {
        self::log(UserActivity::ACTION_DELETED, $document, $extra);
    }

    public static function log(string $action, ?Document $document = null, array $extra = []): void
    {
        $properties = $document !== null
            ? array_merge(self::documentPayload($document), $extra)
            : $extra;

        UserActivity::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'document_id' => $document?->id,
            'properties' => $properties !== [] ? $properties : null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * Snapshot display fields so Activity Log can show metadata after hard delete.
     *
     * @return array<string, mixed>
     */
    protected static function documentPayload(Document $document): array
    {
        $document->loadMissing(['project', 'modifiedBy']);
        $meta = DocumentFilenameParser::extractReferenceAndSubject(
            $document->ocr_text,
            (string) $document->file_name
        );

        return [
            'file_name' => $document->file_name,
            'document_type' => $document->document_type,
            'project_id' => $document->project_id,
            'entity_id' => $document->entity_id,
            'reference_no' => $meta['reference_no'] ?? null,
            'subject' => $meta['subject'] ?? null,
            'project_number' => $document->project?->project_number,
            'project_name' => $document->project?->project_name,
            'project_client' => $document->project?->client_name,
            'project_consultant' => $document->project?->consultant,
            'project_discipline' => $document->discipline,
            'modified_date' => format_model_datetime($document, 'updated_at'),
            'modified_by' => $document->modifiedBy?->username,
            'created_date' => format_model_datetime($document, 'created_at'),
            'created_by' => self::resolveCreatedByUsername($document),
            'file_size' => self::formatFileSize(DocumentPreviewUrl::fileSizeBytes($document)),
        ];
    }

    protected static function resolveCreatedByUsername(Document $document): ?string
    {
        $upload = UserActivity::query()
            ->where('document_id', $document->id)
            ->where('action', UserActivity::ACTION_UPLOADED)
            ->with('user:id,username')
            ->orderBy('id')
            ->first();

        return $upload?->user?->username
            ?? Auth::user()?->username;
    }

    protected static function formatFileSize(?int $bytes): ?string
    {
        if ($bytes === null || $bytes < 0) {
            return null;
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
