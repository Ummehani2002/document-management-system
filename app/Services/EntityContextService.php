<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class EntityContextService
{
    public const SESSION_KEY = 'current_entity_id';

    public const RECENT_UPLOADS_PER_ENTITY = 5;

    public function __construct(
        protected DocumentAccessService $access
    ) {}

    public function get(?User $user): ?Entity
    {
        $entityId = $this->getId($user);

        if ($entityId === null) {
            return null;
        }

        return Entity::query()->find($entityId);
    }

    public function getId(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $entityId = session(self::SESSION_KEY);

        if (! is_numeric($entityId) || (int) $entityId <= 0) {
            return null;
        }

        $entityId = (int) $entityId;

        if (! $this->access->canAccessEntity($user, $entityId)) {
            $this->clear();

            return null;
        }

        return $entityId;
    }

    public function set(User $user, int $entityId): void
    {
        if (! $this->access->canAccessEntity($user, $entityId)) {
            abort(403, 'You do not have access to this entity.');
        }

        session([self::SESSION_KEY => $entityId]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, Entity>
     */
    public function accessibleEntities(?User $user): Collection
    {
        $query = Entity::query()->orderBy('name');

        if ($user !== null && ! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            if ($entityIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $entityIds);
            }
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @return array<int, int>
     */
    public function documentCountsByEntity(?User $user): array
    {
        $query = Document::query()
            ->selectRaw('entity_id, COUNT(*) as total')
            ->groupBy('entity_id');

        if ($user !== null) {
            $this->access->scopeAccessible($query, $user);
        }

        return $query->pluck('total', 'entity_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function projectCountsByEntity(?User $user): array
    {
        $query = Entity::query()
            ->select('entities.id')
            ->selectRaw('COUNT(projects.id) as projects_count')
            ->leftJoin('projects', function ($join): void {
                $join->on('projects.entity_id', '=', 'entities.id')
                    ->whereNull('projects.deleted_at');
            })
            ->groupBy('entities.id');

        if ($user !== null && ! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            if ($entityIds === []) {
                return [];
            }

            $query->whereIn('entities.id', $entityIds);

            $selectedProjectIds = $this->access->selectedProjectIdsForUser($user);
            if ($selectedProjectIds !== []) {
                $query->where(function ($builder) use ($selectedProjectIds): void {
                    $builder->whereIn('projects.id', $selectedProjectIds)
                        ->orWhereNull('projects.id');
                });
            }
        }

        return $query->pluck('projects_count', 'id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return Collection<int, Document>
     */
    public function recentDocumentsForEntity(?User $user, int $entityId, ?int $limit = null): Collection
    {
        $limit ??= self::RECENT_UPLOADS_PER_ENTITY;

        $query = Document::query()
            ->with(['project'])
            ->where('entity_id', $entityId)
            ->latest()
            ->limit($limit);

        if ($user !== null) {
            $this->access->scopeAccessible($query, $user);
        }

        return $query->get();
    }

    /**
     * @return array<int, Collection<int, Document>>
     */
    public function recentDocumentsByEntity(?User $user, ?int $limit = null): array
    {
        $limit ??= self::RECENT_UPLOADS_PER_ENTITY;
        $recentByEntity = [];

        foreach ($this->accessibleEntities($user) as $entity) {
            $recentByEntity[$entity->id] = $this->recentDocumentsForEntity($user, (int) $entity->id, $limit);
        }

        return $recentByEntity;
    }
}
