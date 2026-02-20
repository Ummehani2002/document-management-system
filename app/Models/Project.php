<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'entity_id',
        'project_number',
        'project_name',
        'client_name',
        'consultant',
        'project_manager',
        'document_controller',
    ];

    public function entity()
{
    return $this->belongsTo(Entity::class);
}

public function documents()
{
    return $this->hasMany(Document::class);
}
}
