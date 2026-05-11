<?php

namespace App\Models;

use App\Services\DocumentFilenameParser;
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

    /**
     * Single source of truth for the "Folder" label shown in lists.
     *
     * Delegates to DocumentFilenameParser::folderSubLabel so the dashboard,
     * search results and project dashboard all surface the same folder for
     * a given row.
     */
    public function getDisplayFolderAttribute(): string
    {
        return DocumentFilenameParser::folderSubLabel(
            $this->document_type,
            $this->file_name,
            $this->ocr_text
        );
    }
}
