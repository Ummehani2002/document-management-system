<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSubfolder extends Model
{
    protected $fillable = [
        'main_folder_id',
        'name',
        'auto_keywords',
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

    /**
     * Keywords used by Auto upload to detect this folder (always includes the folder name).
     *
     * @return list<string>
     */
    public function parsedAutoKeywords(): array
    {
        $raw = trim((string) ($this->auto_keywords ?? ''));
        $parts = preg_split('/[\n,;]+/u', $raw) ?: [];
        $keywords = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $keywords[] = $part;
            }
        }

        $name = trim((string) $this->name);
        if ($name !== '') {
            array_unshift($keywords, $name);
        }

        $unique = [];
        foreach ($keywords as $keyword) {
            $key = mb_strtolower($keyword);
            if (! isset($unique[$key])) {
                $unique[$key] = $keyword;
            }
        }

        return array_values($unique);
    }

    public static function normalizeAutoKeywordsInput(?string $input, string $folderName): string
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return trim($folderName);
        }

        $parts = preg_split('/[\n,;]+/u', $raw) ?: [];
        $keywords = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $keywords[] = $part;
            }
        }

        if ($keywords === []) {
            return trim($folderName);
        }

        return implode(', ', array_values(array_unique($keywords)));
    }
}
