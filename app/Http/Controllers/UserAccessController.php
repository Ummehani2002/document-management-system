<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\DocumentFilenameParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserAccessController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access
    ) {}

    public function index()
    {
        $users = User::query()
            ->with(['roles', 'entityAccess.entity', 'projectAccess.project', 'documentAccess'])
            ->orderBy('name')
            ->paginate(20);

        return view('user-access.index', compact('users'));
    }

    public function create()
    {
        $entities = $this->entitiesWithProjects();
        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $roles = Role::orderBy('name')->pluck('name');
        $selectedProjectIds = array_map('intval', old('project_ids', []));
        $selectedFoldersByProject = $this->normalizeFoldersInput(old('folders', []));

        return view('user-access.create', compact(
            'entities',
            'folderTree',
            'roles',
            'selectedProjectIds',
            'selectedFoldersByProject'
        ));
    }

    public function store(Request $request)
    {
        $validRoles = Role::pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['nullable', 'string', 'in:'.implode(',', $validRoles)],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'folders' => ['nullable', 'array'],
            'folders.*' => ['nullable', 'array'],
            'folders.*.*' => ['string', 'max:255'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::create([
            'name' => trim($validated['name']),
            'email' => $email,
            'username' => $email,
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ]);

        $this->applyAccessFromRequest($user, $validated);

        return redirect()
            ->route('user-access.index')
            ->with('success', 'User '.$user->name.' added. They can sign in with Microsoft using '.$user->email.'.');
    }

    public function edit(User $user)
    {
        $user->load(['roles', 'entityAccess', 'folderAccess', 'projectAccess', 'documentAccess.document']);
        $entities = $this->entitiesWithProjects();
        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $roles = Role::orderBy('name')->pluck('name');
        $selectedProjectIds = $this->access->selectedProjectIdsForUser($user);
        $selectedFoldersByProject = $this->access->selectedFolderKeysByProject($user);
        $selectedDocumentIds = $this->access->selectedDocumentIdsForUser($user);
        $grantedDocuments = Document::query()
            ->whereIn('id', $selectedDocumentIds)
            ->orderByDesc('created_at')
            ->get(['id', 'file_name', 'document_type']);

        return view('user-access.edit', compact(
            'user',
            'entities',
            'folderTree',
            'roles',
            'selectedProjectIds',
            'selectedFoldersByProject',
            'selectedDocumentIds',
            'grantedDocuments'
        ));
    }

    public function update(Request $request, User $user)
    {
        $validRoles = Role::pluck('name')->all();

        $validated = $request->validate([
            'role' => ['nullable', 'string', 'in:'.implode(',', $validRoles)],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'folders' => ['nullable', 'array'],
            'folders.*' => ['nullable', 'array'],
            'folders.*.*' => ['string', 'max:255'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ]);

        $this->applyAccessFromRequest($user, $validated);

        return redirect()
            ->route('user-access.index')
            ->with('success', 'Access updated for '.$user->name.'.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function applyAccessFromRequest(User $user, array $validated): void
    {
        $role = $validated['role'] ?? null;
        if ($role !== null && $role !== '') {
            $user->syncRoles([$role]);
        } else {
            $user->syncRoles([]);
        }

        if ($user->hasRole('Admin')) {
            $user->entityAccess()->delete();
            $user->projectAccess()->delete();
            $user->folderAccess()->delete();
            $user->documentAccess()->delete();

            return;
        }

        $this->access->syncUserProjectAccess(
            $user,
            $validated['project_ids'] ?? [],
            $this->normalizeFoldersInput($validated['folders'] ?? [])
        );
        $this->access->syncUserDocumentAccess($user, $validated['document_ids'] ?? []);
    }

    /**
     * @param  array<mixed, mixed>  $folders
     * @return array<int, list<string>>
     */
    protected function normalizeFoldersInput(array $folders): array
    {
        $normalized = [];

        foreach ($folders as $projectId => $keys) {
            if (! is_array($keys)) {
                continue;
            }

            $normalized[(int) $projectId] = array_values(array_filter($keys, 'is_string'));
        }

        return $normalized;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Entity>
     */
    protected function entitiesWithProjects()
    {
        return Entity::query()
            ->with(['projects' => fn ($query) => $query->orderBy('project_number')])
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
