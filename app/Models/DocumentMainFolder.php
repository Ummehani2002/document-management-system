<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentMainFolder extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function subfolders(): HasMany
    {
        return $this->hasMany(DocumentSubfolder::class, 'main_folder_id')->orderBy('sort_order')->orderBy('name');
    }
}
