<?php

namespace App\Models;

use App\Services\DocumentFilenameParser;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Document extends Model
{
    /**
     * Persist timestamps in UTC; display via LocalDateTime / app timezone.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->timezone('UTC')->format('Y-m-d H:i:s');
    }

    /**
     * @param  mixed  $value
     */
    protected function asDateTime($value): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->timezone(config('app.timezone', 'Asia/Dubai'));
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value, 'UTC')->timezone(config('app.timezone', 'Asia/Dubai'));
        }

        return parent::asDateTime($value);
    }

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
