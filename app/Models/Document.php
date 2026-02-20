<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'entity_id',
        'project_id',
        'discipline',
        'document_type',
        'file_name',
        'file_path',
        'ocr_text',
    ];

    public function entity()
{
    return $this->belongsTo(Entity::class);
}

public function project()
{
    return $this->belongsTo(Project::class);
}
}
