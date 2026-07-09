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
        'project_manager_email',
        'document_controller',
        'document_controller_email',
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
