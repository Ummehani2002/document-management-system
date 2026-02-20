<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('entity')->withCount('documents');
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }
        $projects = $query->orderBy('project_name')->paginate(15)->withQueryString();
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        return view('projects.index', compact('projects', 'entities'));
    }

    public function create()
    {
        $entities = Entity::orderBy('name')->get();
        return view('projects.create', compact('entities'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'project_number' => 'required|string|max:100|unique:projects,project_number',
            'project_name' => 'required|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'consultant' => 'nullable|string|max:255',
            'project_manager' => 'nullable|string|max:255',
            'document_controller' => 'nullable|string|max:255',
        ]);
        Project::create($request->only([
            'entity_id', 'project_number', 'project_name',
            'client_name', 'consultant', 'project_manager', 'document_controller',
        ]));
        return redirect()->route('projects.index')->with('success', 'Project created. You can now upload PDFs whose file name starts with "' . $request->project_number . '".');
    }

    public function edit(Project $project)
    {
        $entities = Entity::orderBy('name')->get();
        return view('projects.edit', compact('project', 'entities'));
    }

    public function update(Request $request, Project $project)
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'project_number' => 'required|string|max:100|unique:projects,project_number,' . $project->id,
            'project_name' => 'required|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'consultant' => 'nullable|string|max:255',
            'project_manager' => 'nullable|string|max:255',
            'document_controller' => 'nullable|string|max:255',
        ]);
        $project->update($request->only([
            'entity_id', 'project_number', 'project_name',
            'client_name', 'consultant', 'project_manager', 'document_controller',
        ]));
        return redirect()->route('projects.index')->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }
}
