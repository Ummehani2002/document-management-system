<?php

namespace App\Http\Controllers;

use App\Services\BusinessSectorService;
use App\Services\DocumentAccessService;
use App\Services\EntityContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access,
        protected EntityContextService $entityContext,
        protected BusinessSectorService $businessSector
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        // Home is never inside a company workspace.
        $this->entityContext->clear();

        $selectedSector = $this->businessSector->get();
        $allEntities = $this->entityContext->accessibleEntities($user);
        $entities = $selectedSector !== null
            ? $this->businessSector->filterEntities($allEntities, $selectedSector)
            : $allEntities;

        $documentCounts = $this->entityContext->documentCountsByEntity($user);
        $projectCounts = $this->entityContext->projectCountsByEntity($user);
        $recentByEntity = $this->entityContext->recentDocumentsByEntity($user);

        $statsEntityIds = $entities->pluck('id')->all();
        $totalDocuments = collect($statsEntityIds)->sum(fn ($id) => $documentCounts[$id] ?? 0);
        $totalProjects = collect($statsEntityIds)->sum(fn ($id) => $projectCounts[$id] ?? 0);
        $totalEntities = $entities->count();

        $entityCards = $entities->map(function ($entity) use ($documentCounts, $projectCounts, $recentByEntity) {
            return (object) [
                'id' => $entity->id,
                'name' => $entity->name,
                'initials' => entity_initials($entity->name),
                'logo_url' => entity_logo_url($entity->name),
                'documents_count' => $documentCounts[$entity->id] ?? 0,
                'projects_count' => $projectCounts[$entity->id] ?? 0,
                'recent_documents' => $recentByEntity[$entity->id] ?? collect(),
            ];
        });

        $sectorCounts = $this->businessSector->countsForEntities($allEntities);
        $tradingCount = $sectorCounts[BusinessSectorService::TRADING];
        $constructionCount = $sectorCounts[BusinessSectorService::CONSTRUCTION];

        return view('dashboard', [
            'totalDocuments' => $totalDocuments,
            'totalProjects' => $totalProjects,
            'totalEntities' => $totalEntities,
            'entityCards' => $entityCards,
            'isAdmin' => $this->access->isAdmin($user),
            'selectedSector' => $selectedSector,
            'selectedSectorLabel' => $this->businessSector->label($selectedSector),
            'tradingCount' => $tradingCount,
            'constructionCount' => $constructionCount,
        ]);
    }

    public function selectSector(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sector' => 'required|in:trading,construction',
        ]);

        $this->entityContext->clear();
        $this->businessSector->set($validated['sector']);

        return redirect()->route('dashboard');
    }

    public function clearSector(Request $request): RedirectResponse
    {
        $this->entityContext->clear();
        $this->businessSector->clear();

        return redirect()->route('dashboard');
    }
}
