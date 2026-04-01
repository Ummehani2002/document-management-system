<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $projectNumber = trim((string) $request->query('project_number', ''));
        $project = null;
        $documents = collect();

        if ($projectNumber !== '') {
            $project = Project::query()
                ->whereRaw('LOWER(TRIM(project_number)) = LOWER(?)', [$projectNumber])
                ->first();

            if ($project) {
                $documents = $project->documents()
                    ->with('entity')
                    ->orderBy('file_name')
                    ->get();
            }
        }

        return view('project-dashboard', [
            'projectNumberQuery' => $projectNumber,
            'project' => $project,
            'documents' => $documents,
        ]);
    }
}
