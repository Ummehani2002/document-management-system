<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DocumentDeletionService
{
    /**
     * Soft-delete a document: keep the file and DB row so it can be restored.
     *
     * @param  array<string, mixed>  $extra
     */
    public function delete(Document $document, array $extra = []): void
    {
        UserActivityLogger::deleted($document, $extra);
        $document->delete();
    }

    /**
     * Permanently remove storage + DB row (cannot be restored).
     *
     * @param  array<string, mixed>  $extra
     */
    public function forceDelete(Document $document, array $extra = []): void
    {
        if (! $document->trashed()) {
            UserActivityLogger::deleted($document, array_merge($extra, [
                'permanent' => true,
            ]));
        } else {
            UserActivityLogger::deleted($document, array_merge($extra, [
                'permanent' => true,
                'from_trash' => true,
            ]));
        }

        $this->deleteStoredFile($document);
        $document->forceDelete();
    }

    /**
     * Restore a soft-deleted document.
     *
     * @param  array<string, mixed>  $extra
     */
    public function restore(Document $document, array $extra = []): void
    {
        if (! $document->trashed()) {
            return;
        }

        $document->restore();
        UserActivityLogger::restored($document, $extra);
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
     * Permanently delete every document belonging to a project (project itself is being removed).
     *
     * @param  array<string, mixed>  $extra
     */
    public function deleteForProject(int $projectId, array $extra = []): int
    {
        return $this->forceDeleteQuery(
            Document::withTrashed()->where('project_id', $projectId),
            array_merge($extra, ['deleted_via' => $extra['deleted_via'] ?? 'project'])
        );
    }

    /**
     * Permanently delete every document belonging to an entity (entity itself is being removed).
     *
     * @param  array<string, mixed>  $extra
     */
    public function deleteForEntity(int $entityId, array $extra = []): int
    {
        return $this->forceDeleteQuery(
            Document::withTrashed()->where('entity_id', $entityId),
            array_merge($extra, ['deleted_via' => $extra['deleted_via'] ?? 'entity'])
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Document>  $query
     * @param  array<string, mixed>  $extra
     */
    protected function forceDeleteQuery($query, array $extra = []): int
    {
        $count = 0;

        $query->orderBy('id')->chunkById(100, function (Collection $documents) use (&$count, $extra) {
            foreach ($documents as $document) {
                $this->forceDelete($document, $extra);
                $count++;
            }
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
