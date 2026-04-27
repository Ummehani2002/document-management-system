<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        if ($search === '') {
            $search = trim((string) $request->query('project_number', ''));
        }

        $projectId = (int) $request->query('project_id', 0);

        $project = null;
        $projects = collect();
        $documents = collect();

        if ($projectId > 0) {
            $project = Project::query()
                ->with('entity')
                ->withCount('documents')
                ->find($projectId);

            if ($project) {
                $documents = $project->documents()
                    ->with('entity')
                    ->orderBy('file_name')
                    ->get();
            }
        } elseif ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $trimmed = trim($search);

            $matches = Project::query()
                ->with('entity')
                ->withCount('documents')
                ->where(function ($q) use ($trimmed, $like) {
                    $q->whereRaw('LOWER(TRIM(project_number)) = LOWER(?)', [$trimmed])
                        ->orWhereRaw('LOWER(project_number) LIKE LOWER(?)', [$like])
                        ->orWhereRaw('LOWER(project_name) LIKE LOWER(?)', [$like]);
                })
                ->orderBy('project_number')
                ->get();

            if ($matches->count() === 1) {
                $project = $matches->first();
                $documents = $project->documents()
                    ->with('entity')
                    ->orderBy('file_name')
                    ->get();
            } elseif ($matches->count() > 1) {
                $projects = $matches;
            }
        }

        $pdfCount = $project
            ? (int) ($project->documents_count ?? $project->documents()->count())
            : 0;

        return view('project-dashboard', [
            'searchQuery' => $search,
            'projectNumberQuery' => $search,
            'project' => $project,
            'projects' => $projects,
            'documents' => $documents,
            'pdfCount' => $pdfCount,
        ]);
    }
}
