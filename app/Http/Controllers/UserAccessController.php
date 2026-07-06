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
            ->with(['roles', 'entityAccess.entity', 'documentAccess'])
            ->orderBy('name')
            ->paginate(20);

        return view('user-access.index', compact('users'));
    }

    public function create()
    {
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $roles = Role::orderBy('name')->pluck('name');

        return view('user-access.create', compact('entities', 'folderTree', 'roles'));
    }

    public function store(Request $request)
    {
        $validRoles = Role::pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['nullable', 'string', 'in:'.implode(',', $validRoles)],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', 'exists:entities,id'],
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

    public function edit(Request $request, User $user)
    {
        $user->load(['roles', 'entityAccess', 'folderAccess', 'documentAccess.document']);
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $roles = Role::orderBy('name')->pluck('name');
        $selectedEntityIds = $user->entityAccess->pluck('entity_id')->map(fn ($id) => (int) $id)->all();
        $selectedFolders = $this->access->selectedFolderKeysForUser($user);
        $selectedDocumentIds = $this->access->selectedDocumentIdsForUser($user);
        $grantedDocuments = Document::query()
            ->whereIn('id', $selectedDocumentIds)
            ->orderByDesc('created_at')
            ->get(['id', 'file_name', 'document_type']);

        $documentSearch = trim((string) $request->query('doc_q', ''));
        $documentResults = collect();
        if ($documentSearch !== '') {
            $documentResults = Document::query()
                ->with('entity:id,name')
                ->where('file_name', 'like', '%'.$documentSearch.'%')
                ->orderByDesc('created_at')
                ->limit(40)
                ->get(['id', 'file_name', 'document_type', 'entity_id']);
        }

        return view('user-access.edit', compact(
            'user',
            'entities',
            'folderTree',
            'roles',
            'selectedEntityIds',
            'selectedFolders',
            'selectedDocumentIds',
            'grantedDocuments',
            'documentSearch',
            'documentResults'
        ));
    }

    public function update(Request $request, User $user)
    {
        $validRoles = Role::pluck('name')->all();

        $validated = $request->validate([
            'role' => ['nullable', 'string', 'in:'.implode(',', $validRoles)],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', 'exists:entities,id'],
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
            $user->folderAccess()->delete();
            $user->documentAccess()->delete();

            return;
        }

        $entityIds = array_map('intval', $validated['entity_ids'] ?? []);
        $selectedFolders = [];

        foreach ($entityIds as $entityId) {
            $keys = $validated['folders'][$entityId] ?? [];
            if (is_array($keys)) {
                $selectedFolders[$entityId] = array_values(array_filter($keys, 'is_string'));
            }
        }

        $this->access->syncUserAccess($user, $entityIds, $selectedFolders);
        $this->access->syncUserDocumentAccess($user, $validated['document_ids'] ?? []);
    }
}
