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

        $allEntities = $this->entityContext->accessibleEntities($user);
        $documentCounts = $this->entityContext->documentCountsByEntity($user);
        $projectCounts = $this->entityContext->projectCountsByEntity($user);

        $totalDocuments = collect($allEntities->pluck('id'))->sum(fn ($id) => $documentCounts[$id] ?? 0);
        $totalProjects = collect($allEntities->pluck('id'))->sum(fn ($id) => $projectCounts[$id] ?? 0);
        $totalEntities = $allEntities->count();

        $entityCards = $allEntities->map(function ($entity) use ($documentCounts, $projectCounts) {
            $sector = $this->businessSector->sectorForEntity($entity);

            return (object) [
                'id' => $entity->id,
                'name' => $entity->name,
                'initials' => $entity->displayCode(),
                'logo_url' => entity_logo_url($entity->name),
                'sector' => $sector,
                'sector_label' => $this->businessSector->label($sector),
                'documents_count' => $documentCounts[$entity->id] ?? 0,
                'projects_count' => $projectCounts[$entity->id] ?? 0,
            ];
        });

        $sectorCounts = $this->businessSector->countsForEntities($allEntities);

        $firstName = trim((string) strtok((string) ($user->name ?? ''), ' '));
        if ($firstName === '') {
            $firstName = 'there';
        }

        $now = now()->timezone(config('app.timezone', 'Asia/Dubai'));
        $hour = (int) $now->format('G');
        $greetingPeriod = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');

        return view('dashboard', [
            'totalDocuments' => $totalDocuments,
            'totalProjects' => $totalProjects,
            'totalEntities' => $totalEntities,
            'entityCards' => $entityCards,
            'isAdmin' => $this->access->isAdmin($user),
            'tradingCount' => $sectorCounts[BusinessSectorService::TRADING],
            'constructionCount' => $sectorCounts[BusinessSectorService::CONSTRUCTION],
            'greetingName' => $firstName,
            'greetingPeriod' => $greetingPeriod,
            'greetingDate' => $now->format('l, j F Y'),
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
