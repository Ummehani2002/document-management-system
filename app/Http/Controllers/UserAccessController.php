<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\DocumentFilenameParser;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserAccessController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access
    ) {}

    public function index()
    {
        $users = User::query()
            ->with(['roles', 'entityAccess.entity'])
            ->orderBy('name')
            ->paginate(20);

        return view('user-access.index', compact('users'));
    }

    public function edit(User $user)
    {
        $user->load(['roles', 'entityAccess', 'folderAccess']);
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $roles = Role::orderBy('name')->pluck('name');
        $selectedEntityIds = $user->entityAccess->pluck('entity_id')->map(fn ($id) => (int) $id)->all();
        $selectedFolders = $this->access->selectedFolderKeysForUser($user);

        return view('user-access.edit', compact(
            'user',
            'entities',
            'folderTree',
            'roles',
            'selectedEntityIds',
            'selectedFolders'
        ));
    }

    public function update(Request $request, User $user)
    {
        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $validRoles = Role::pluck('name')->all();

        $validated = $request->validate([
            'role' => ['nullable', 'string', 'in:'.implode(',', $validRoles)],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', 'exists:entities,id'],
            'folders' => ['nullable', 'array'],
            'folders.*' => ['nullable', 'array'],
            'folders.*.*' => ['string', 'max:255'],
        ]);

        $role = $validated['role'] ?? null;
        if ($role !== null && $role !== '') {
            $user->syncRoles([$role]);
        } else {
            $user->syncRoles([]);
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

        return redirect()
            ->route('user-access.index')
            ->with('success', 'Access updated for '.$user->name.'.');
    }
}
