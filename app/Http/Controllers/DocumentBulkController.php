<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentAccessService;
use App\Services\DocumentBulkService;
use App\Services\DocumentDeletionService;
use App\Services\DocumentFilenameParser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Actions on several selected documents at once: download (ZIP), move, copy and delete.
 */
class DocumentBulkController extends Controller
{
    protected const MAX_SELECTION = 500;

    public function __construct(
        protected DocumentAccessService $access,
        protected DocumentBulkService $bulk
    ) {}

    public function download(Request $request)
    {
        $documents = $this->selectedDocuments($request);

        if ($documents->count() === 1) {
            return redirect()->route('documents.download', ['id' => $documents->first()->id]);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $zipPath = $this->bulk->buildZip($documents);
        if ($zipPath === null) {
            return back()->withErrors(['bulk' => 'None of the selected files are available in storage.']);
        }

        $zipName = 'documents-'.now()->format('Ymd-His').'.zip';

        return response()->download($zipPath, $zipName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function destroy(Request $request, DocumentDeletionService $deletions)
    {
        $documents = $this->selectedDocuments($request);
        $count = $deletions->deleteMany($documents, ['bulk' => true]);

        return back()->with('success', $count.' file(s) moved to Trash. You can restore them from Trash or the Activity Log.');
    }

    public function move(Request $request)
    {
        $documents = $this->selectedDocuments($request);
        [$entity, $project, $documentType] = $this->resolveTarget($request);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $moved = 0;
        $failed = 0;
        foreach ($documents as $document) {
            $this->bulk->move($document, $entity, $project, $documentType) ? $moved++ : $failed++;
        }

        return back()->with('success', $this->resultMessage($moved, $failed, 'moved', $project, $documentType));
    }

    public function copy(Request $request)
    {
        $documents = $this->selectedDocuments($request);
        [$entity, $project, $documentType] = $this->resolveTarget($request);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $copied = 0;
        $failed = 0;
        foreach ($documents as $document) {
            $this->bulk->copy($document, $entity, $project, $documentType) !== null ? $copied++ : $failed++;
        }

        return back()->with('success', $this->resultMessage($copied, $failed, 'copied', $project, $documentType));
    }

    /**
     * @return Collection<int, Document>
     */
    protected function selectedDocuments(Request $request): Collection
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:'.self::MAX_SELECTION,
            'ids.*' => 'integer',
        ], [
            'ids.required' => 'Select at least one document.',
        ]);

        $documents = Document::query()->whereIn('id', array_unique($validated['ids']))->get();
        if ($documents->isEmpty()) {
            abort(404, 'Selected documents were not found.');
        }

        foreach ($documents as $document) {
            if (! $this->access->canAccessDocument(Auth::user(), $document)) {
                abort(403, 'You do not have access to one or more selected documents.');
            }
        }

        return $documents;
    }

    /**
     * @return array{0: Entity, 1: Project, 2: string}
     */
    protected function resolveTarget(Request $request): array
    {
        $folderTree = DocumentFilenameParser::sidebarFolderTree();

        $validated = $request->validate([
            'target_entity_id' => 'required|exists:entities,id',
            'target_project_id' => 'required|exists:projects,id',
            'target_main_folder' => ['required', 'string', Rule::in(array_keys($folderTree))],
            'target_document_type' => 'required|string',
        ]);

        $mainFolder = $validated['target_main_folder'];
        $documentType = $validated['target_document_type'];
        if (! in_array($documentType, $folderTree[$mainFolder] ?? [], true)) {
            abort(422, 'Selected folder does not belong to the selected category.');
        }

        $entity = Entity::findOrFail((int) $validated['target_entity_id']);
        $project = Project::where('id', (int) $validated['target_project_id'])
            ->where('entity_id', $entity->id)
            ->firstOrFail();

        $user = Auth::user();
        if (! $this->access->canAccessEntity($user, (int) $entity->id)) {
            abort(403, 'You do not have access to the selected entity.');
        }
        if (! $this->access->isAdmin($user)
            && in_array((int) $entity->id, $this->access->entitiesWithProjectRestrictions($user), true)
            && ! in_array((int) $project->id, $this->access->allowedProjectIdsForEntity($user, (int) $entity->id), true)) {
            abort(403, 'You do not have access to the selected project.');
        }
        if (! $this->access->canAccessFolder($user, (int) $entity->id, $mainFolder, $documentType)) {
            abort(403, 'You do not have permission to put files in the selected folder.');
        }

        return [$entity, $project, $documentType];
    }

    protected function resultMessage(int $done, int $failed, string $verb, Project $project, string $documentType): string
    {
        $msg = $done.' file(s) '.$verb.' to '.$project->project_number.' / '.$documentType.'.';
        if ($failed > 0) {
            $msg .= ' '.$failed.' file(s) could not be '.$verb.' because the file is missing from storage.';
        }

        return $msg;
    }
}
