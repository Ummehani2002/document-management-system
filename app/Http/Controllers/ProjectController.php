<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentAccessService;
use App\Services\DocumentDeletionService;
use App\Services\EntityContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access,
        protected EntityContextService $entityContext
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $entityId = $this->entityContext->getId($user);

        if ($entityId === null) {
            abort(403, 'Select a company to continue.');
        }

        $query = Project::with('entity')
            ->withCount('documents')
            ->where('entity_id', $entityId);

        if (! $this->access->isAdmin($user)) {
            $restrictedEntityIds = $this->access->entitiesWithProjectRestrictions($user);
            if (in_array($entityId, $restrictedEntityIds, true)) {
                $allowedProjectIds = $this->access->allowedProjectIdsForEntity($user, $entityId);
                $query->whereIn('id', $allowedProjectIds);
            }
        }

        $projects = $query->orderBy('project_name')->paginate(15)->withQueryString();
        $entity = Entity::query()->findOrFail($entityId);

        return view('projects.index', compact('projects', 'entity'));
    }

    public function create()
    {
        $user = Auth::user();
        $currentEntityId = $this->entityContext->getId($user);

        $entityQuery = Entity::orderBy('name');
        if ($currentEntityId !== null) {
            $entityQuery->where('id', $currentEntityId);
        } elseif (! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            $entityQuery->whereIn('id', $entityIds);
        }

        $entities = $entityQuery->get();

        return view('projects.create', compact('entities', 'currentEntityId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $currentEntityId = $this->entityContext->getId($user);

        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'project_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('projects', 'project_number')->whereNull('deleted_at'),
            ],
            'project_name' => 'required|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'consultant' => 'nullable|string|max:255',
            'project_manager' => 'nullable|string|max:255',
            'project_manager_email' => 'nullable|email|max:255',
            'document_controller' => 'nullable|string|max:255',
            'document_controller_email' => 'nullable|email|max:255',
        ]);

        if ($currentEntityId !== null && (int) $request->entity_id !== $currentEntityId) {
            abort(403, 'You can only add projects to the current company.');
        }

        if (! $this->access->canAccessEntity($user, (int) $request->entity_id)) {
            abort(403, 'You do not have access to this entity.');
        }

        Project::create($request->only([
            'entity_id', 'project_number', 'project_name',
            'client_name', 'consultant', 'project_manager', 'project_manager_email',
            'document_controller', 'document_controller_email',
        ]));

        return $this->projectRedirect(
            'Project created. You can now upload PDFs whose file name starts with "'.$request->project_number.'".'
        );
    }

    public function edit(Project $project)
    {
        $user = Auth::user();
        $currentEntityId = $this->entityContext->getId($user);

        if ($currentEntityId !== null && (int) $project->entity_id !== $currentEntityId) {
            abort(403, 'This project belongs to another company.');
        }

        if (! $this->access->canAccessEntity($user, (int) $project->entity_id)) {
            abort(403, 'You do not have access to this project.');
        }

        $entityQuery = Entity::orderBy('name');
        if ($currentEntityId !== null) {
            $entityQuery->where('id', $currentEntityId);
        } elseif (! $this->access->isAdmin($user)) {
            $entityQuery->whereIn('id', $this->access->accessibleEntityIds($user));
        }

        $entities = $entityQuery->get();

        return view('projects.edit', compact('project', 'entities', 'currentEntityId'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $user = Auth::user();
        $currentEntityId = $this->entityContext->getId($user);

        if ($currentEntityId !== null && (int) $project->entity_id !== $currentEntityId) {
            abort(403, 'This project belongs to another company.');
        }

        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'project_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('projects', 'project_number')
                    ->ignore($project->id)
                    ->whereNull('deleted_at'),
            ],
            'project_name' => 'required|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'consultant' => 'nullable|string|max:255',
            'project_manager' => 'nullable|string|max:255',
            'project_manager_email' => 'nullable|email|max:255',
            'document_controller' => 'nullable|string|max:255',
            'document_controller_email' => 'nullable|email|max:255',
        ]);

        if ($currentEntityId !== null && (int) $request->entity_id !== $currentEntityId) {
            abort(403, 'You can only assign projects to the current company.');
        }

        if (! $this->access->canAccessEntity($user, (int) $request->entity_id)) {
            abort(403, 'You do not have access to this entity.');
        }

        $project->update($request->only([
            'entity_id', 'project_number', 'project_name',
            'client_name', 'consultant', 'project_manager', 'project_manager_email',
            'document_controller', 'document_controller_email',
        ]));

        return $this->projectRedirect('Project updated.');
    }

    public function destroy(Project $project, DocumentDeletionService $deletions): RedirectResponse
    {
        abort_unless($this->access->isAdmin(Auth::user()), 403);

        $deletedDocs = $deletions->deleteForProject($project->id, [
            'deleted_via' => 'project',
        ]);
        $project->delete();

        $message = $deletedDocs > 0
            ? "Project deleted. {$deletedDocs} document(s) moved to Trash and can be restored."
            : 'Project deleted.';

        return $this->projectRedirect($message);
    }

    protected function projectRedirect(string $message): RedirectResponse
    {
        if ($this->entityContext->getId(Auth::user()) !== null) {
            return redirect()->route('projects.index')->with('success', $message);
        }

        return redirect()->route('dashboard')->with('success', $message);
    }
}
