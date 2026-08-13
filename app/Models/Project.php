<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

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
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
