<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSubfolder extends Model
{
    protected $fillable = [
        'main_folder_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function mainFolder(): BelongsTo
    {
        return $this->belongsTo(DocumentMainFolder::class, 'main_folder_id');
    }
}
