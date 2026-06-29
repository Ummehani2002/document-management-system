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
     * @return array<string, mixed>
     */
    protected static function documentPayload(Document $document): array
    {
        return [
            'file_name' => $document->file_name,
            'document_type' => $document->document_type,
            'project_id' => $document->project_id,
            'entity_id' => $document->entity_id,
        ];
    }
}
