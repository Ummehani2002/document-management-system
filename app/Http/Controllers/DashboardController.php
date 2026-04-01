<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $entityId = (int) $request->query('entity_id', 0);
        $totalDocuments = Document::count();
        $totalProjects = Project::count();
        $totalEntities = Entity::count();
        $entities = Entity::query()->orderBy('name')->get(['id', 'name']);

        $documentsPerProject = Project::withCount('documents')
            ->orderByDesc('documents_count')
            ->limit(10)
            ->get();

        $documentsByType = Document::query()
            ->selectRaw('document_type, count(*) as total')
            ->whereNotNull('document_type')
            ->where('document_type', '!=', '')
            ->groupBy('document_type')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $recentDocumentsQuery = Document::with(['project', 'entity']);
        if ($entityId > 0) {
            $recentDocumentsQuery->where('entity_id', $entityId);
        }

        $recentDocuments = $recentDocumentsQuery
            ->latest()
            ->limit(20)
            ->get();

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
