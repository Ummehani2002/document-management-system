<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\BusinessSectorService;
use App\Services\DocumentAccessService;
use App\Services\DocumentLocationResolver;
use App\Services\EntityContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EntityWorkspaceController extends Controller
{
    public function __construct(
        protected EntityContextService $entityContext,
        protected DocumentAccessService $access,
        protected BusinessSectorService $businessSector
    ) {}

    public function enter(Request $request, Entity $entity): RedirectResponse
    {
        $entitySector = $this->businessSector->sectorForEntity($entity);

        $this->entityContext->set($request->user(), (int) $entity->id);
        $this->businessSector->set($entitySector);

        return redirect()->route('workspace');
    }

    public function exit(Request $request): RedirectResponse
    {
        $this->entityContext->clear();

        return redirect()->route('dashboard');
    }

    public function show(Request $request): View
    {
        $user = $request->user();
        $entity = $this->entityContext->get($user);

        if ($entity === null) {
            abort(404);
        }

        $entityId = (int) $entity->id;

        $projectsQuery = Project::query()
            ->where('entity_id', $entityId)
            ->withCount(['documents' => function ($query) use ($user): void {
                $this->access->scopeAccessible($query, $user);
            }])
            ->orderBy('project_number');

        if (! $this->access->isAdmin($user)) {
            $restrictedEntityIds = $this->access->entitiesWithProjectRestrictions($user);
            if (in_array($entityId, $restrictedEntityIds, true)) {
                $allowedProjectIds = $this->access->allowedProjectIdsForEntity($user, $entityId);
                $projectsQuery->whereIn('id', $allowedProjectIds);
            }
        }

        $projects = $projectsQuery->get();

        $totalDocumentsQuery = Document::query()->where('entity_id', $entityId);
        $this->access->scopeAccessible($totalDocumentsQuery, $user);
        $totalDocuments = $totalDocumentsQuery->count();

        $weekStart = now()->timezone(config('app.timezone', 'Asia/Dubai'))->startOfWeek();
        $uploadedThisWeekQuery = Document::query()
            ->where('entity_id', $entityId)
            ->where('created_at', '>=', $weekStart);
        $this->access->scopeAccessible($uploadedThisWeekQuery, $user);
        $uploadedThisWeek = $uploadedThisWeekQuery->count();

        $recentDocuments = $this->entityContext->recentDocumentsForEntity($user, $entityId);
        $recentDocuments->loadMissing(['modifiedBy', 'project']);

        $recentDocuments->transform(function (Document $document) {
            $document->file_available = DocumentLocationResolver::resolve((string) $document->file_path) !== null;

            return $document;
        });

        $entitySector = $this->businessSector->sectorForEntity($entity);

        return view('workspace', [
            'entity' => $entity,
            'projects' => $projects,
            'totalDocuments' => $totalDocuments,
            'totalProjects' => $projects->count(),
            'uploadedThisWeek' => $uploadedThisWeek,
            'recentDocuments' => $recentDocuments,
            'entitySector' => $entitySector,
            'entitySectorLabel' => $this->businessSector->label($entitySector),
        ]);
    }
}
