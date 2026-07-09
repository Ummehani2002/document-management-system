<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Models\UserFolderAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentAccessService
{
    public function isAdmin(?User $user): bool
    {
        return $user !== null && $user->hasRole('Admin');
    }

    /**
     * @return list<int>
     */
    public function accessibleEntityIds(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($this->isAdmin($user)) {
            return [];
        }

        return $user->entityAccess()
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canAccessEntity(?User $user, int $entityId): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->entityAccess()->where('entity_id', $entityId)->exists();
    }

    /**
     * @return Collection<int, UserFolderAccess>
     */
    public function folderAccessForEntity(User $user, int $entityId): Collection
    {
        return $user->folderAccess()
            ->where('entity_id', $entityId)
            ->whereNull('project_id')
            ->get();
    }

  /**
     * @return Collection<int, UserFolderAccess>
     */
    public function folderAccessForProject(User $user, int $projectId): Collection
    {
        return $user->folderAccess()->where('project_id', $projectId)->get();
    }

    /**
     * @return list<int>
     */
    public function allowedProjectIdsForEntity(User $user, int $entityId): array
    {
        return $user->projectAccess()
            ->whereHas('project', fn (Builder $query) => $query->where('entity_id', $entityId))
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function entitiesWithProjectRestrictions(User $user): array
    {
        return $user->projectAccess()
            ->join('projects', 'projects.id', '=', 'user_project_access.project_id')
            ->distinct()
            ->pluck('projects.entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function selectedProjectIdsForUser(User $user): array
    {
        return $user->projectAccess()
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function entitiesWithFolderRestrictions(User $user): array
    {
        return $user->folderAccess()
            ->whereNull('project_id')
            ->distinct()
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function projectsWithFolderRestrictions(User $user): array
    {
        return $user->folderAccess()
            ->whereNotNull('project_id')
            ->distinct()
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allowedDocumentTypesForEntity(User $user, int $entityId): array
    {
        $rows = $this->folderAccessForEntity($user, $entityId);
        if ($rows->isEmpty()) {
            return [];
        }

        return $this->documentTypesFromFolderRows($rows);
    }

    /**
     * @param  Collection<int, UserFolderAccess>  $rows
     * @return list<string>
     */
    public function documentTypesFromFolderRows(Collection $rows): array
    {
        $tree = DocumentFilenameParser::sidebarFolderTree();
        $types = [];

        foreach ($rows as $row) {
            if ($row->document_type === null) {
                $subs = $tree[$row->main_folder] ?? [];
                $types = array_merge($types, $subs);
            } else {
                $types[] = $row->document_type;
            }
        }

        return array_values(array_unique($types));
    }

    public function canAccessFolder(?User $user, int $entityId, string $mainFolder, ?string $documentType): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $this->canAccessEntity($user, $entityId)) {
            return false;
        }

        $rows = $this->folderAccessForEntity($user, $entityId);
        if ($rows->isEmpty()) {
            return true;
        }

        if ($documentType !== null && $documentType !== '') {
            $allowed = $this->documentTypesFromFolderRows($rows);

            return in_array($documentType, $allowed, true);
        }

        foreach ($rows as $row) {
            if ($row->main_folder === $mainFolder) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function grantedDocumentIds(User $user): array
    {
        return $user->documentAccess()
            ->pluck('document_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canAccessDocument(?User $user, Document $document): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (in_array((int) $document->id, $this->grantedDocumentIds($user), true)) {
            return true;
        }

        if (! $this->canAccessEntity($user, (int) $document->entity_id)) {
            return false;
        }

        $allowedProjectIds = $this->allowedProjectIdsForEntity($user, (int) $document->entity_id);
        if ($allowedProjectIds !== [] && ! in_array((int) $document->project_id, $allowedProjectIds, true)) {
            return false;
        }

        $rows = $this->folderAccessForProject($user, (int) $document->project_id);
        if ($rows->isEmpty()) {
            $rows = $this->folderAccessForEntity($user, (int) $document->entity_id);
        }
        if ($rows->isEmpty()) {
            return true;
        }

        $allowed = $this->documentTypesFromFolderRows($rows);
        $docType = (string) ($document->document_type ?? '');

        if ($docType !== '' && in_array($docType, $allowed, true)) {
            return true;
        }

        $mainFolder = DocumentFilenameParser::mainFolderForDocumentType($docType);

        foreach ($rows as $row) {
            if ($row->document_type === null && $row->main_folder === $mainFolder) {
                return true;
            }
        }

        return false;
    }

    public function scopeAccessible(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isAdmin($user)) {
            return $query;
        }

        $entityIds = $this->accessibleEntityIds($user);
        $grantedDocIds = $this->grantedDocumentIds($user);

        if ($entityIds === [] && $grantedDocIds === []) {
            return $query->whereRaw('1 = 0');
        }

        if ($entityIds === []) {
            return $query->whereIn('id', $grantedDocIds);
        }

        $restricted = $this->entitiesWithFolderRestrictions($user);
        $projectRestricted = $this->entitiesWithProjectRestrictions($user);
        $restrictedEntityIds = array_values(array_unique(array_merge($restricted, $projectRestricted)));
        $unrestricted = array_values(array_diff($entityIds, $restrictedEntityIds));

        return $query->where(function (Builder $outer) use ($user, $unrestricted, $restrictedEntityIds, $grantedDocIds): void {
            if ($grantedDocIds !== []) {
                $outer->orWhereIn('id', $grantedDocIds);
            }

            if ($unrestricted !== []) {
                $outer->orWhereIn('entity_id', $unrestricted);
            }

            foreach ($restrictedEntityIds as $entityId) {
                $outer->orWhere(function (Builder $inner) use ($user, $entityId): void {
                    $inner->where('entity_id', $entityId);
                    $this->applyEntityAccessFilterToQuery($inner, $user, $entityId);
                });
            }
        });
    }

    public function applyEntityAccessFilterToQuery(Builder $query, User $user, int $entityId): void
    {
        $projectIds = $this->allowedProjectIdsForEntity($user, $entityId);
        if ($projectIds !== []) {
            $query->where(function (Builder $projectOuter) use ($user, $projectIds): void {
                foreach ($projectIds as $projectId) {
                    $projectOuter->orWhere(function (Builder $projectInner) use ($user, $projectId): void {
                        $projectInner->where('project_id', $projectId);
                        $this->applyFolderFilterForProject($projectInner, $user, $projectId);
                    });
                }
            });

            return;
        }

        $this->applyFolderFilterToQuery($query, $user, $entityId);
    }

    public function applyFolderFilterForProject(Builder $query, User $user, int $projectId): void
    {
        $rows = $this->folderAccessForProject($user, $projectId);
        if ($rows->isEmpty()) {
            return;
        }

        $allowed = $this->documentTypesFromFolderRows($rows);
        if ($allowed === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        DocumentFilenameParser::applyFolderTypeFilter($query, $allowed);
    }

    public function applyFolderFilterToQuery(Builder $query, User $user, int $entityId): void
    {
        $allowed = $this->allowedDocumentTypesForEntity($user, $entityId);
        if ($allowed === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        DocumentFilenameParser::applyFolderTypeFilter($query, $allowed);
    }

    /**
     * @return array<string, list<string>>
     */
    public function accessibleSidebarFolderTree(?User $user): array
    {
        $fullTree = DocumentFilenameParser::sidebarFolderTree();

        if ($user === null) {
            return [];
        }

        if ($this->isAdmin($user)) {
            return $fullTree;
        }

        $entityIds = $this->accessibleEntityIds($user);
        if ($entityIds === []) {
            return [];
        }

        $allowedTypes = collect();
        foreach ($entityIds as $entityId) {
            $rows = $this->folderAccessForEntity($user, $entityId);
            if ($rows->isEmpty()) {
                return $fullTree;
            }
            $allowedTypes = $allowedTypes->merge($this->documentTypesFromFolderRows($rows));
        }

        $allowedTypes = $allowedTypes->unique()->values()->all();
        $filtered = [];

        foreach ($fullTree as $mainFolder => $subfolders) {
            $visible = array_values(array_intersect($subfolders, $allowedTypes));
            if ($visible !== []) {
                $filtered[$mainFolder] = $visible;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, list<string>>
     */
    public function accessibleFolderTreeForEntity(?User $user, int $entityId): array
    {
        $fullTree = DocumentFilenameParser::sidebarFolderTree();

        if ($user === null) {
            return [];
        }

        if ($this->isAdmin($user)) {
            return $fullTree;
        }

        if (! $this->canAccessEntity($user, $entityId)) {
            return [];
        }

        $rows = $this->folderAccessForEntity($user, $entityId);
        if ($rows->isEmpty()) {
            return $fullTree;
        }

        $allowed = $this->documentTypesFromFolderRows($rows);
        $filtered = [];

        foreach ($fullTree as $mainFolder => $subfolders) {
            $visible = array_values(array_intersect($subfolders, $allowed));
            if ($visible !== []) {
                $filtered[$mainFolder] = $visible;
            }
        }

        return $filtered;
    }

    /**
     * @param  array<int, list<string>>  $selectedFoldersByProject  project_id => list of "main_folder|document_type" keys
     */
    public function syncUserProjectAccess(User $user, array $projectIds, array $selectedFoldersByProject): void
    {
        $user->entityAccess()->delete();
        $user->projectAccess()->delete();
        $user->folderAccess()->delete();

        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));
        if ($projectIds === []) {
            return;
        }

        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->get(['id', 'entity_id']);

        foreach ($projects->pluck('entity_id')->unique() as $entityId) {
            $user->entityAccess()->create(['entity_id' => (int) $entityId]);
        }

        $tree = DocumentFilenameParser::sidebarFolderTree();

        foreach ($projects as $project) {
            $user->projectAccess()->create(['project_id' => $project->id]);

            $keys = $selectedFoldersByProject[$project->id]
                ?? $selectedFoldersByProject[(string) $project->id]
                ?? [];
            if ($keys === []) {
                continue;
            }

            $mainSelections = [];
            foreach ($keys as $key) {
                if (! is_string($key) || ! str_contains($key, '|')) {
                    continue;
                }
                [$main, $sub] = explode('|', $key, 2);
                if (! isset($tree[$main])) {
                    continue;
                }
                $mainSelections[$main][] = $sub;
            }

            foreach ($mainSelections as $mainFolder => $subs) {
                $validSubs = $tree[$mainFolder] ?? [];
                $subs = array_values(array_intersect($subs, $validSubs));
                if ($subs === []) {
                    continue;
                }

                if (count($subs) === count($validSubs)) {
                    $user->folderAccess()->create([
                        'entity_id' => $project->entity_id,
                        'project_id' => $project->id,
                        'main_folder' => $mainFolder,
                        'document_type' => null,
                    ]);
                } else {
                    foreach ($subs as $sub) {
                        $user->folderAccess()->create([
                            'entity_id' => $project->entity_id,
                            'project_id' => $project->id,
                            'main_folder' => $mainFolder,
                            'document_type' => $sub,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Build selected folder keys grouped by project for the access form.
     *
     * @return array<int, list<string>>
     */
    public function selectedFolderKeysByProject(User $user): array
    {
        $tree = DocumentFilenameParser::sidebarFolderTree();
        $result = [];

        foreach ($user->folderAccess as $row) {
            if ($row->project_id === null) {
                continue;
            }

            $projectId = (int) $row->project_id;
            if ($row->document_type === null) {
                foreach ($tree[$row->main_folder] ?? [] as $sub) {
                    $result[$projectId][] = $row->main_folder.'|'.$sub;
                }
            } else {
                $result[$projectId][] = $row->main_folder.'|'.$row->document_type;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, list<string>>  $selectedFolders  entity_id => list of "main_folder|document_type" keys
     */
    public function syncUserAccess(User $user, array $entityIds, array $selectedFolders): void
    {
        $user->entityAccess()->delete();
        $user->projectAccess()->delete();
        $user->folderAccess()->delete();

        $validEntityIds = array_map('intval', $entityIds);
        $tree = DocumentFilenameParser::sidebarFolderTree();

        foreach ($validEntityIds as $entityId) {
            $user->entityAccess()->create(['entity_id' => $entityId]);

            $keys = $selectedFolders[$entityId] ?? [];
            if ($keys === []) {
                continue;
            }

            $mainSelections = [];
            foreach ($keys as $key) {
                if (! is_string($key) || ! str_contains($key, '|')) {
                    continue;
                }
                [$main, $sub] = explode('|', $key, 2);
                if (! isset($tree[$main])) {
                    continue;
                }
                $mainSelections[$main][] = $sub;
            }

            foreach ($mainSelections as $mainFolder => $subs) {
                $validSubs = $tree[$mainFolder] ?? [];
                $subs = array_values(array_intersect($subs, $validSubs));
                if ($subs === []) {
                    continue;
                }

                if (count($subs) === count($validSubs)) {
                    $user->folderAccess()->create([
                        'entity_id' => $entityId,
                        'main_folder' => $mainFolder,
                        'document_type' => null,
                    ]);
                } else {
                    foreach ($subs as $sub) {
                        $user->folderAccess()->create([
                            'entity_id' => $entityId,
                            'main_folder' => $mainFolder,
                            'document_type' => $sub,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Build selected folder keys for the edit form.
     *
     * @return array<int, list<string>>
     */
    public function selectedFolderKeysForUser(User $user): array
    {
        $tree = DocumentFilenameParser::sidebarFolderTree();
        $result = [];

        foreach ($user->folderAccess as $row) {
            $entityId = (int) $row->entity_id;
            if ($row->document_type === null) {
                foreach ($tree[$row->main_folder] ?? [] as $sub) {
                    $result[$entityId][] = $row->main_folder.'|'.$sub;
                }
            } else {
                $result[$entityId][] = $row->main_folder.'|'.$row->document_type;
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $documentIds
     */
    public function syncUserDocumentAccess(User $user, array $documentIds): void
    {
        $user->documentAccess()->delete();

        $validIds = Document::query()
            ->whereIn('id', array_map('intval', $documentIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach (array_unique($validIds) as $documentId) {
            $user->documentAccess()->create(['document_id' => $documentId]);
        }
    }

    /**
     * @return list<int>
     */
    public function selectedDocumentIdsForUser(User $user): array
    {
        return $this->grantedDocumentIds($user);
    }
}
