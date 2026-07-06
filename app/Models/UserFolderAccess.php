<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFolderAccess extends Model
{
    protected $table = 'user_folder_access';

    protected $fillable = [
        'user_id',
        'entity_id',
        'main_folder',
        'document_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
