<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    public const ACTION_UPLOADED = 'document.uploaded';

    public const ACTION_REPLACED = 'document.replaced';

    public const ACTION_DELETED = 'document.deleted';

    public const ACTION_REATTACHED = 'document.re_attached';

    /** @var array<string, string> */
    public const ACTION_LABELS = [
        self::ACTION_UPLOADED => 'Document uploaded',
        self::ACTION_REPLACED => 'Document replaced',
        self::ACTION_DELETED => 'Document deleted',
        self::ACTION_REATTACHED => 'Document re-attached',
    ];

    protected $fillable = [
        'user_id',
        'action',
        'document_id',
        'properties',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
