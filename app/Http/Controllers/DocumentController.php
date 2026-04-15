<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Jobs\ProcessOCR;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentFileVersioning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /** Document categories = folder names under entity/project */
    protected static function documentCategories(): array
    {
        return [
            'Document Transmittal',
            'Form',
            'Method Submittal',
            'Drawing',
            'Specification',
            'Report',
            'Correspondence',
            'Other',
        
        ];
    }

    public function create()
    {
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $projects = Project::with('entity:id,name')->orderBy('project_number')->get();
        return view('documents.upload', compact('entities', 'projects'));
    }

    /**
     * Suggest entity, project, and folder from a document filename (e.g. PSE20231011-PRS-PAR-DTF-00056 R.00 - Monthly Progress Report....pdf).
     */
    public function suggestFromFilename(Request $request)
    {
        $filename = $request->input('filename', '');
        $filename = is_string($filename) ? trim($filename) : '';
        if ($filename === '') {
            return response()->json(['entity_id' => null, 'project_id' => null, 'document_category' => 'Other', 'message' => 'No filename']);
        }
        $suggestion = DocumentFilenameParser::suggestPlacementMerged($filename, null);
        return response()->json($suggestion);
    }

    public function store(Request $request)
    {
        $request->validate([
            'entity_id'         => 'required|exists:entities,id',
            'project_id'        => 'required|exists:projects,id',
            'documents'         => 'required|array|min:1',
            'documents.*'       => 'file|mimes:pdf|max:51200',
        ]);

        // Multi-file upload + per-file OCR can exceed default max_execution_time; allow the request to finish.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $entity = Entity::findOrFail($request->entity_id);
        $project = Project::where('id', $request->project_id)->where('entity_id', $entity->id)->firstOrFail();

        $files = $request->file('documents', []);
        if (! is_array($files)) {
            $files = array_filter([$files]);
        }

        $uploaded = 0;
        $skippedDuplicates = 0;
        $detectedFolders = [];
        $detectedProjects = [];
        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $suggestedPlacement = DocumentFilenameParser::suggestPlacementMerged($originalName, null);
            $targetEntity = $entity;
            $targetProject = $project;

            // If filename contains a known project number, auto-route this file to that project/entity.
            if (!empty($suggestedPlacement['project_id'])) {
                $matchedProject = Project::with('entity')->find((int) $suggestedPlacement['project_id']);
                if ($matchedProject && $matchedProject->entity) {
                    $targetProject = $matchedProject;
                    $targetEntity = $matchedProject->entity;
                }
            }

            $category = $suggestedPlacement['document_category'] ?? 'Other';

            $folderPath = 'documents/'
                . Str::slug($targetEntity->name) . '/'
                . Str::slug($targetProject->project_number) . '/'
                . Str::slug($category);

            // Duplicate policy:
            // - same logical filename + same content => skip as already uploaded
            // - same logical filename + different content => continue and create next version
            $uploadedHash = $this->uploadedFileHash($file);
            if ($uploadedHash !== null) {
                $targetKey = DocumentFileVersioning::versionKey(pathinfo($originalName, PATHINFO_FILENAME));
                $candidates = Document::query()
                    ->where('project_id', $targetProject->id)
                    ->where('document_type', $category)
                    ->get(['file_name', 'file_path']);

                $isDuplicate = false;
                foreach ($candidates as $candidate) {
                    $existingBase = pathinfo((string) $candidate->file_name, PATHINFO_FILENAME);
                    if (DocumentFileVersioning::versionKey($existingBase) !== $targetKey) {
                        continue;
                    }
                    $existingHash = $this->storedFileHash((string) $candidate->file_path);
                    if ($existingHash !== null && hash_equals($uploadedHash, $existingHash)) {
                        $isDuplicate = true;
                        break;
                    }
                }

                if ($isDuplicate) {
                    $skippedDuplicates++;
                    continue;
                }
            }

            $storedFileName = DocumentFileVersioning::buildVersionedFilename($originalName, $targetProject->id, $category);
            $path = $file->storeAs($folderPath, $storedFileName);

            $document = Document::create([
                'entity_id'     => $targetEntity->id,
                'project_id'    => $targetProject->id,
                'discipline'    => null,
                'document_type' => $category,
                'file_name'     => $storedFileName,
                'file_path'     => $path,
            ]);
            // Run OCR on the sync connection after the redirect so uploads are not blocked (works without a queue worker).
            try {
                ProcessOCR::dispatch($document->id)
                    ->onConnection('sync')
                    ->afterResponse();
            } catch (\Throwable $e) {
                \Log::warning('ProcessOCR on upload failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            }
            $uploaded++;
            $detectedFolders[$category] = true;
            $detectedProjects[$targetProject->project_number] = true;
        }

        $msg = $uploaded === 1
            ? '1 file uploaded successfully.'
            : $uploaded.' files uploaded successfully.';
        if ($skippedDuplicates > 0) {
            $msg .= ' '.$skippedDuplicates.' duplicate file(s) were already uploaded and skipped.';
        }

        return back()->with('success', $msg);
    }

    protected function uploadedFileHash($file): ?string
    {
        $realPath = $file?->getRealPath();
        if (!$realPath || !is_file($realPath)) {
            return null;
        }
        $hash = @hash_file('sha256', $realPath);

        return is_string($hash) ? $hash : null;
    }

    protected function storedFileHash(string $path): ?string
    {
        $location = $this->resolveDocumentLocation($path);
        if ($location === null) {
            return null;
        }

        if ($location['source'] === 'disk') {
            $stream = Storage::disk($location['disk'])->readStream($location['path']);
            if ($stream === false || !is_resource($stream)) {
                return null;
            }
        } else {
            $stream = @fopen($location['path'], 'rb');
            if ($stream === false || !is_resource($stream)) {
                return null;
            }
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    public function search(Request $request)
    {
        $keyword = trim($request->keyword ?? '');
        $projectId = $request->project_id ? (int) $request->project_id : null;
        $entityId = $request->entity_id ? (int) $request->entity_id : null;
        $discipline = trim($request->discipline ?? '');
        $documentType = trim($request->document_type ?? '');
        $fromSidebar = (bool) $request->boolean('from_sidebar');
        $needsProjectSelection = $fromSidebar && $documentType !== '' && !$projectId;
        $hasSearchFilters = $keyword !== ''
            || $projectId
            || $entityId
            || $discipline !== ''
            || $documentType !== '';
        $documentTypeAliases = [
            'Method Statement' => ['Method Statement', 'Method Submittal'],
            'Shop Drawing' => ['Shop Drawing', 'Drawing'],
            'Incoming Or Outgoing Letter' => ['Incoming Or Outgoing Letter', 'Correspondence'],
            'Monthly Report' => ['Monthly Report', 'Report'],
        ];
        $documentTypeFilters = $documentType !== ''
            ? ($documentTypeAliases[$documentType] ?? [$documentType])
            : [];

        // Project options in dropdown should come from Project Master.
        $projectsQuery = Project::query()->orderBy('project_name');
        if ($entityId) {
            $projectsQuery->where('entity_id', $entityId);
        }
        $projects = $projectsQuery->get(['id', 'entity_id', 'project_number', 'project_name']);
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $disciplines = Document::whereNotNull('discipline')->where('discipline', '!=', '')
            ->distinct()->orderBy('discipline')->pluck('discipline');
        $documentTypes = Document::whereNotNull('document_type')->where('document_type', '!=', '')
            ->distinct()->orderBy('document_type')->pluck('document_type');

        $documents = null;
        if (!$needsProjectSelection && $hasSearchFilters) {
            $query = Document::with(['project', 'entity']);

            if ($entityId) {
                $query->where('entity_id', $entityId);
            }
            if ($projectId) {
                $query->where('project_id', $projectId);
            }
            if ($discipline !== '') {
                $query->where('discipline', $discipline);
            }
            if (!empty($documentTypeFilters)) {
                $query->whereIn('document_type', $documentTypeFilters);
            }

            if ($keyword !== '') {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
                $driver = DB::connection()->getDriverName();
             
                $query->where(function ($q) use ($keyword, $like, $driver) {
                    if ($driver === 'mysql') {
                        $q->whereRaw('MATCH(ocr_text) AGAINST(? IN NATURAL LANGUAGE MODE)', [$keyword])
                          ->orWhereRaw('LOWER(ocr_text) LIKE LOWER(?)', [$like])
                          ->orWhereRaw('LOWER(file_name) LIKE LOWER(?)', [$like])
                          ->orWhere('document_type', 'like', $like)
                          ->orWhereHas('entity', fn ($eq) => $eq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                          ->orWhereHas('project', fn ($pq) => $pq
                              ->whereRaw('LOWER(project_number) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(project_name) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(client_name) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(consultant) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(project_manager) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(document_controller) LIKE LOWER(?)', [$like]));
                    } else {
                        $q->whereRaw('LOWER(ocr_text) LIKE LOWER(?)', [$like])
                          ->orWhereRaw('LOWER(file_name) LIKE LOWER(?)', [$like])
                          ->orWhere('document_type', 'like', $like)
                          ->orWhereHas('entity', fn ($eq) => $eq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                          ->orWhereHas('project', fn ($pq) => $pq
                              ->whereRaw('LOWER(project_number) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(project_name) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(client_name) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(consultant) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(project_manager) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(document_controller) LIKE LOWER(?)', [$like]));
                    }
                });
            }

            $documents = $query->paginate(10)->withQueryString();
        }

        $totalDocuments = Document::count();
        $documentsWithoutOcr = Document::where(function ($q) {
            $q->whereNull('ocr_text')->orWhere('ocr_text', '');
        })->count();

        return view('documents.search', compact(
            'documents', 'keyword', 'projects', 'entities',
            'disciplines', 'documentTypes', 'totalDocuments', 'documentsWithoutOcr',
            'fromSidebar', 'needsProjectSelection'
        ));
    }

    public function download(int $id)
    {
        $document = Document::find($id);

        if (!$document) {
            abort(404, 'Document not found. Use Search to find a PDF and click Download from the result.');
        }

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);

        if ($location === null) {
            abort(404, 'File not found on disk: ' . $path);
        }

        if ($location['source'] === 'disk') {
            return Storage::disk($location['disk'])->download($location['path'], $document->file_name, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response()->download($location['path'], $document->file_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Serve PDF with inline disposition so the browser can display it in a tab. */
    public function viewPdf(int $id)
    {
        $document = Document::find($id);

        if (!$document) {
            abort(404, 'Document not found.');
        }

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);

        if ($location === null) {
            abort(404, 'File not found on disk: ' . $path);
        }

        if ($location['source'] === 'disk') {
            return Storage::disk($location['disk'])->response(
                $location['path'],
                $document->file_name,
                ['Content-Type' => 'application/pdf'],
                'inline'
            );
        }

        return response()->file(
            $location['path'],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function destroy(int $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return back()->with('success', 'File not found');
        }

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);
        if ($location !== null) {
            if ($location['source'] === 'disk') {
                Storage::disk($location['disk'])->delete($location['path']);
            } else {
                @unlink($location['path']);
            }
        }

        $document->delete();

        return back()->with('success', 'File successfully deleted');
    }

    /**
     * Resolve a document path across the configured disk and legacy local paths.
     *
     * @return array{source:'disk',disk:string,path:string}|array{source:'file',path:string}|null
     */
    protected function resolveDocumentLocation(string $path): ?array
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        if ($normalizedPath === '') {
            return null;
        }

        $candidateDisks = array_values(array_unique(array_filter([
            config('filesystems.default'),
            'local',
            'public',
        ])));

        foreach ($candidateDisks as $disk) {
            if (Storage::disk($disk)->exists($normalizedPath)) {
                return ['source' => 'disk', 'disk' => $disk, 'path' => $normalizedPath];
            }
        }

        $absoluteCandidates = array_unique([
            storage_path('app/' . $normalizedPath),
            storage_path('app/private/' . $normalizedPath),
            storage_path('app/public/' . $normalizedPath),
        ]);

        foreach ($absoluteCandidates as $absolutePath) {
            if (is_file($absolutePath)) {
                return ['source' => 'file', 'path' => $absolutePath];
            }
        }

        return null;
    }
}