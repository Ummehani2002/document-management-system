<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentAccessService;
use App\Services\DocumentLocationResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $entityId = (int) $request->query('entity_id', 0);

        $entitiesQuery = Entity::query()->orderBy('name');
        if (! $this->access->isAdmin($user)) {
            $accessibleEntityIds = $this->access->accessibleEntityIds($user);
            if ($accessibleEntityIds === []) {
                $entitiesQuery->whereRaw('1 = 0');
            } else {
                $entitiesQuery->whereIn('id', $accessibleEntityIds);
            }

            if ($entityId > 0 && ! $this->access->canAccessEntity($user, $entityId)) {
                abort(403, 'You do not have access to this entity.');
            }
        }
        $entities = $entitiesQuery->get(['id', 'name']);

        $totalDocumentsQuery = Document::query();
        $this->access->scopeAccessible($totalDocumentsQuery, $user);
        $totalDocuments = $totalDocumentsQuery->count();

        $projectsQuery = Project::query();
        if (! $this->access->isAdmin($user)) {
            $accessibleEntityIds = $this->access->accessibleEntityIds($user);
            $selectedProjectIds = $this->access->selectedProjectIdsForUser($user);
            if ($selectedProjectIds !== []) {
                $projectsQuery->whereIn('id', $selectedProjectIds);
            } elseif ($accessibleEntityIds !== []) {
                $projectsQuery->whereIn('entity_id', $accessibleEntityIds);
            } else {
                $projectsQuery->whereRaw('1 = 0');
            }
        }
        $totalProjects = $projectsQuery->count();
        $totalEntities = $entities->count();

        $documentsPerProjectQuery = Project::withCount(['documents' => function ($query) use ($user): void {
            $this->access->scopeAccessible($query, $user);
        }]);
        if (! $this->access->isAdmin($user)) {
            $accessibleEntityIds = $this->access->accessibleEntityIds($user);
            $selectedProjectIds = $this->access->selectedProjectIdsForUser($user);
            if ($selectedProjectIds !== []) {
                $documentsPerProjectQuery->whereIn('id', $selectedProjectIds);
            } elseif ($accessibleEntityIds !== []) {
                $documentsPerProjectQuery->whereIn('entity_id', $accessibleEntityIds);
            } else {
                $documentsPerProjectQuery->whereRaw('1 = 0');
            }
        }
        $documentsPerProject = $documentsPerProjectQuery
            ->orderByDesc('documents_count')
            ->limit(10)
            ->get();

        $documentsByTypeQuery = Document::query()
            ->selectRaw('document_type, count(*) as total')
            ->whereNotNull('document_type')
            ->where('document_type', '!=', '')
            ->groupBy('document_type')
            ->orderByDesc('total')
            ->limit(10);
        $this->access->scopeAccessible($documentsByTypeQuery, $user);
        $documentsByType = $documentsByTypeQuery->get();

        $recentDocumentsQuery = Document::with(['project', 'entity']);
        $this->access->scopeAccessible($recentDocumentsQuery, $user);
        if ($entityId > 0) {
            $recentDocumentsQuery->where('entity_id', $entityId);
        }

        $recentDocuments = $recentDocumentsQuery
            ->latest()
            ->limit(20)
            ->get();

        $recentDocuments->transform(function (Document $document) {
            $document->file_available = DocumentLocationResolver::resolve((string) $document->file_path) !== null;

            return $document;
        });

        return view('dashboard', [
            'totalDocuments' => $totalDocuments,
            'totalProjects' => $totalProjects,
            'totalEntities' => $totalEntities,
            'documentsPerProject' => $documentsPerProject,
            'documentsByType' => $documentsByType,
            'entities' => $entities,
            'selectedEntityId' => $entityId,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
