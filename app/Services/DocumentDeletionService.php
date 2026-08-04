<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DocumentDeletionService
{
    /**
     * Log activity (while metadata/file still exist), remove storage, then hard-delete the row.
     *
     * @param  array<string, mixed>  $extra
     */
    public function delete(Document $document, array $extra = []): void
    {
        UserActivityLogger::deleted($document, $extra);
        $this->deleteStoredFile($document);
        $document->delete();
    }

    /**
     * @param  iterable<int, Document>  $documents
     * @param  array<string, mixed>  $extra
     */
    public function deleteMany(iterable $documents, array $extra = []): int
    {
        $count = 0;

        foreach ($documents as $document) {
            $this->delete($document, $extra);
            $count++;
        }

        return $count;
    }

    /**
     * Delete every document belonging to a project (storage + activity + DB).
     *
     * @param  array<string, mixed>  $extra
     */
    public function deleteForProject(int $projectId, array $extra = []): int
    {
        return $this->deleteQuery(
            Document::query()->where('project_id', $projectId),
            $extra
        );
    }

    /**
     * Delete every document belonging to an entity (storage + activity + DB).
     *
     * @param  array<string, mixed>  $extra
     */
    public function deleteForEntity(int $entityId, array $extra = []): int
    {
        return $this->deleteQuery(
            Document::query()->where('entity_id', $entityId),
            $extra
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Document>  $query
     * @param  array<string, mixed>  $extra
     */
    protected function deleteQuery($query, array $extra = []): int
    {
        $count = 0;

        $query->orderBy('id')->chunkById(100, function (Collection $documents) use (&$count, $extra) {
            $count += $this->deleteMany($documents, $extra);
        });

        return $count;
    }

    public function deleteStoredFile(Document $document): void
    {
        $location = DocumentLocationResolver::resolve((string) $document->file_path);
        if ($location === null) {
            return;
        }

        if (($location['source'] ?? '') === 'disk') {
            Storage::disk($location['disk'])->delete($location['path']);

            return;
        }

        @unlink($location['path']);
    }
}
