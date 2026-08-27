<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use App\Services\DocumentAccessService;
use App\Services\EntityContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access,
        protected EntityContextService $entityContext
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $entities = $this->entityContext->accessibleEntities($user);
        $documentCounts = $this->entityContext->documentCountsByEntity($user);
        $projectCounts = $this->entityContext->projectCountsByEntity($user);
        $recentByEntity = $this->entityContext->recentDocumentsByEntity($user);

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

        $entityCards = $entities->map(function ($entity) use ($documentCounts, $projectCounts, $recentByEntity) {
            return (object) [
                'id' => $entity->id,
                'name' => $entity->name,
                'initials' => entity_initials($entity->name),
                'documents_count' => $documentCounts[$entity->id] ?? 0,
                'projects_count' => $projectCounts[$entity->id] ?? 0,
                'recent_documents' => $recentByEntity[$entity->id] ?? collect(),
            ];
        });

        return view('dashboard', [
            'totalDocuments' => $totalDocuments,
            'totalProjects' => $totalProjects,
            'totalEntities' => $entities->count(),
            'entityCards' => $entityCards,
            'isAdmin' => $this->access->isAdmin($user),
        ]);
    }
}
