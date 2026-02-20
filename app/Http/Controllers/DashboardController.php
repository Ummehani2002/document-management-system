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
        $totalDocuments = Document::count();
        $totalProjects = Project::count();
        $totalEntities = Entity::count();

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

        $recentDocuments = Document::with(['project', 'entity'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard', [
            'totalDocuments' => $totalDocuments,
            'totalProjects' => $totalProjects,
            'totalEntities' => $totalEntities,
            'documentsPerProject' => $documentsPerProject,
            'documentsByType' => $documentsByType,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
