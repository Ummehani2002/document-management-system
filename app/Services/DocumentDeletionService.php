<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
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
        UserActivityLogger::deleted($document, array_merge($extra, [
            'permanent' => true,
            'from_trash' => $document->trashed(),
        ]));

        $this->deleteStoredFile($document);
        $document->forceDelete();
    }

    /**
     * Restore a soft-deleted document (and its project/entity if they were soft-deleted with it).
     *
     * @param  array<string, mixed>  $extra
     */
    public function restore(Document $document, array $extra = []): void
    {
        if (! $document->trashed()) {
            return;
        }

        $this->restoreProjectAndEntityFor($document);
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
     * Soft-delete every document belonging to a project (recoverable from Trash).
     *
     * @param  array<string, mixed>  $extra
     */
    public function deleteForProject(int $projectId, array $extra = []): int
    {
        return $this->softDeleteQuery(
            Document::query()->where('project_id', $projectId),
            array_merge($extra, ['deleted_via' => $extra['deleted_via'] ?? 'project'])
        );
    }

    /**
     * Soft-delete every document belonging to an entity (recoverable from Trash).
     *
     * @param  array<string, mixed>  $extra
     */
    public function deleteForEntity(int $entityId, array $extra = []): int
    {
        $count = $this->softDeleteQuery(
            Document::query()->where('entity_id', $entityId),
            array_merge($extra, ['deleted_via' => $extra['deleted_via'] ?? 'entity'])
        );

        Project::query()->where('entity_id', $entityId)->orderBy('id')->chunkById(100, function (Collection $projects) {
            foreach ($projects as $project) {
                $project->delete();
            }
        });

        return $count;
    }

    protected function restoreProjectAndEntityFor(Document $document): void
    {
        $project = Project::withTrashed()->find($document->project_id);
        if ($project === null) {
            return;
        }

        if ($project->trashed()) {
            $entity = Entity::withTrashed()->find($project->entity_id);
            if ($entity !== null && $entity->trashed()) {
                $entity->restore();
            }
            $project->restore();
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Document>  $query
     * @param  array<string, mixed>  $extra
     */
    protected function softDeleteQuery($query, array $extra = []): int
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
