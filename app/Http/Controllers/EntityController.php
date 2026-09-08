<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Services\DocumentAccessService;
use App\Services\DocumentDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EntityController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access
    ) {}

    public function index()
    {
        $user = Auth::user();
        $query = Entity::withCount('projects')->orderBy('name');

        if (! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            if ($entityIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $entityIds);
            }
        }

        $entities = $query->paginate(15);

        return view('entities.index', compact('entities'));
    }

    public function create()
    {
        return view('entities.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'required|in:trading,construction',
        ]);

        Entity::create($validated);

        return redirect()->route('entities.index')->with('success', 'Entity created.');
    }

    public function edit(Entity $entity)
    {
        return view('entities.edit', compact('entity'));
    }

    public function update(Request $request, Entity $entity)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'required|in:trading,construction',
        ]);

        $entity->update($validated);

        return redirect()->route('entities.index')->with('success', 'Entity updated.');
    }

    public function destroy(Entity $entity, DocumentDeletionService $deletions, DocumentAccessService $access)
    {
        abort_unless($access->isAdmin(Auth::user()), 403);

        $deletedDocs = $deletions->deleteForEntity($entity->id, [
            'deleted_via' => 'entity',
        ]);
        $entity->delete();

        $message = $deletedDocs > 0
            ? "Entity deleted. {$deletedDocs} document(s) moved to Trash and can be restored."
            : 'Entity deleted.';

        return redirect()->route('entities.index')->with('success', $message);
    }
}
