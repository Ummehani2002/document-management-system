<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentAccessService;
use App\Services\DocumentDeletionService;
use App\Services\DocumentLocationResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DocumentTrashController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access,
        protected DocumentDeletionService $deletions
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $query = Document::onlyTrashed()
            ->with(['project', 'entity', 'modifiedBy'])
            ->latest('deleted_at');

        if (! $this->access->isAdmin($user)) {
            $query->where(function ($q) use ($user) {
                // Non-admins only see trashed docs they can access.
                $entityIds = $this->access->accessibleEntityIds($user);
                if ($entityIds === []) {
                    $q->whereRaw('1 = 0');

                    return;
                }
                $q->whereIn('entity_id', $entityIds);
            });
        }

        $documents = $query->paginate(25)->withQueryString();

        $documents->getCollection()->transform(function (Document $document) {
            $document->file_available = DocumentLocationResolver::resolve((string) $document->file_path) !== null;
            $document->can_restore = $this->access->canAccessDocument(Auth::user(), $document);

            return $document;
        });

        // Filter out inaccessible rows for non-admins (entity-level filter is approximate).
        if (! $this->access->isAdmin($user)) {
            $documents->setCollection(
                $documents->getCollection()->filter(fn (Document $doc) => $doc->can_restore)->values()
            );
        }

        return view('documents.trash', [
            'documents' => $documents,
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $document = Document::onlyTrashed()->find($id);
        if ($document === null) {
            return back()->withErrors(['trash' => 'Deleted file not found in Trash.']);
        }

        if (! $this->access->canAccessDocument(Auth::user(), $document)) {
            abort(403, 'You do not have access to restore this document.');
        }

        $this->deletions->restore($document);

        return back()->with('success', 'File restored successfully.');
    }

    public function forceDestroy(int $id): RedirectResponse
    {
        $document = Document::onlyTrashed()->find($id);
        if ($document === null) {
            return back()->withErrors(['trash' => 'Deleted file not found in Trash.']);
        }

        if (! $this->access->canAccessDocument(Auth::user(), $document)) {
            abort(403, 'You do not have access to permanently delete this document.');
        }

        $this->deletions->forceDelete($document);

        return back()->with('success', 'File permanently deleted.');
    }
}
