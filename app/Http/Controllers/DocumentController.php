<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\UserActivity;
use App\Jobs\ProcessOCR;
use App\Jobs\SendSharedDocumentEmail;
use App\Services\DocumentAccessService;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentFileReplacer;
use App\Services\DocumentFileVersioning;
use App\Services\DocumentLocationResolver;
use App\Services\DocumentPreviewUrl;
use App\Services\MicrosoftGraphMailService;
use App\Services\MicrosoftGraphPeopleService;
use App\Services\DocumentVersionSaver;
use App\Services\OnlyOfficeService;
use App\Services\SharedDocumentMailBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\UploadedFile;
use App\Services\UserActivityLogger;


class DocumentController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access
    ) {}

    protected function authorizeDocument(?Document $document): void
    {
        if ($document === null) {
            abort(404, 'Document not found.');
        }

        if (! $this->access->canAccessDocument(Auth::user(), $document)) {
            abort(403, 'You do not have access to this document.');
        }
    }

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

    public function create(Request $request)
    {
        $user = Auth::user();
        $entityQuery = Entity::orderBy('name');
        if (! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            $entityQuery->whereIn('id', $entityIds);
        }
        $entities = $entityQuery->get(['id', 'name']);
        $projects = Project::with('entity:id,name')->orderBy('project_number');
        if (! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            $projects->whereIn('entity_id', $entityIds);
        }
        $projects = $projects->get();
        $folderTree = $this->access->accessibleSidebarFolderTree($user);
        $folderTreesByEntity = [];
        foreach ($entities as $entity) {
            $folderTreesByEntity[$entity->id] = $this->access->accessibleFolderTreeForEntity($user, (int) $entity->id);
        }
        $disciplines = Discipline::orderBy('name')->get(['id', 'name']);
        $mode = (string) $request->query('mode', old('upload_mode', 'auto'));
        if (! in_array($mode, ['auto', 'manual'], true)) {
            $mode = 'auto';
        }

        $directUploadEnabled = config('filesystems.default') === 's3';
        $directUploadMinMb = max(1, (int) env('DOC_DIRECT_UPLOAD_MIN_MB', 75));

        return view('documents.upload', compact(
            'entities', 'projects', 'folderTree', 'folderTreesByEntity', 'mode', 'disciplines',
            'directUploadEnabled', 'directUploadMinMb'
        ));
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
        $maxFileMb = max(1, (int) env('DOC_UPLOAD_MAX_FILE_MB', 1024)); // default 1 GB per file
        $maxTotalMb = max($maxFileMb, (int) env('DOC_UPLOAD_MAX_TOTAL_MB', 2048)); // default 2 GB total request
        $maxFileKb = $maxFileMb * 1024;

        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $validMainFolders = array_keys($folderTree);
        $validSubfolders = array_values(array_unique(array_merge(
            ['Other'],
            ...array_values($folderTree)
        )));

        $request->validate([
            'upload_mode'       => ['nullable', 'string', Rule::in(['auto', 'manual'])],
            'entity_id'         => 'required|exists:entities,id',
            'project_id'        => 'required|exists:projects,id',
            'discipline_id'     => 'nullable|exists:disciplines,id',
            'main_folder'       => ['nullable', 'string', Rule::in($validMainFolders)],
            'document_type'     => ['nullable', 'string', Rule::in($validSubfolders)],
            'documents'         => 'required|array|min:1',
            'documents.*'       => [
                'file',
                'max:' . $maxFileKb,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$value instanceof UploadedFile) {
                        $fail('Invalid file upload.');
                        return;
                    }

                    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
                    $originalExt = strtolower((string) $value->getClientOriginalExtension());
                    $guessedExt = strtolower((string) $value->guessExtension());

                    if (in_array($originalExt, $allowed, true) || in_array($guessedExt, $allowed, true)) {
                        return;
                    }

                    $fail('The file must be one of: pdf, doc, docx, xls, xlsx.');
                },
            ],
        ]);

        $uploadMode = (string) $request->input('upload_mode', 'auto');
        $manualMainFolder = trim((string) $request->input('main_folder', ''));
        $manualSubfolder = trim((string) $request->input('document_type', ''));
        if ($uploadMode === 'manual') {
            if ($manualMainFolder === '' || $manualSubfolder === '') {
                return back()->withErrors([
                    'main_folder' => 'Select category and folder for manual upload.',
                ])->withInput();
            }
            $allowedSubfolders = $folderTree[$manualMainFolder] ?? [];
            if (!in_array($manualSubfolder, $allowedSubfolders, true)) {
                return back()->withErrors([
                    'document_type' => 'Selected folder does not belong to selected category.',
                ])->withInput();
            }
        }

        // Multi-file upload + per-file OCR can exceed default max_execution_time; allow the request to finish.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $entity = Entity::findOrFail($request->entity_id);
        $project = Project::where('id', $request->project_id)->where('entity_id', $entity->id)->firstOrFail();

        if (! $this->access->canAccessEntity(Auth::user(), (int) $entity->id)) {
            abort(403, 'You do not have access to this entity.');
        }

        $disciplineName = null;
        if ($request->filled('discipline_id')) {
            $disciplineName = Discipline::whereKey((int) $request->discipline_id)->value('name');
        }

        $files = $request->file('documents', []);
        if (! is_array($files)) {
            $files = array_filter([$files]);
        }

        $totalBytes = 0;
        foreach ($files as $uploadedFile) {
            if ($uploadedFile && method_exists($uploadedFile, 'getSize')) {
                $size = (int) $uploadedFile->getSize();
                if ($size > 0) {
                    $totalBytes += $size;
                }
            }
        }
        $maxTotalBytes = $maxTotalMb * 1024 * 1024;
        if ($totalBytes > $maxTotalBytes) {
            return back()->withErrors([
                'documents' => 'Total selected files size is too large. Maximum allowed is ' . $maxTotalMb . ' MB per upload.',
            ])->withInput();
        }

        $uploaded = 0;
        $skippedDuplicates = 0;
        $failedUploads = 0;
        $detectedFolders = [];
        $detectedProjects = [];
        $enableDeepDuplicateCheck = (bool) env('DOC_DEEP_DUP_CHECK', false);
        $disk = config('filesystems.default');
        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $targetEntity = $entity;
            $targetProject = $project;
            $category = 'Other';

            if ($uploadMode === 'manual') {
                $category = $manualSubfolder;
            } else {
                // In auto mode, keep prediction lightweight so upload returns quickly.
                // OCR-based refinement still runs after upload via ProcessOCR.
                $category = $this->predictUploadCategory($file, $originalName, $validSubfolders);
            }

            $mainFolder = $uploadMode === 'manual'
                ? $manualMainFolder
                : (DocumentFilenameParser::mainFolderForDocumentType($category) ?? '');
            if (! $this->access->canAccessFolder(Auth::user(), (int) $entity->id, $mainFolder, $category)) {
                return back()->withErrors([
                    'document_type' => 'You do not have permission to upload to this folder.',
                ])->withInput();
            }

            $folderPath = 'documents/'
                . Str::slug($targetEntity->name) . '/'
                . Str::slug($targetProject->project_number) . '/'
                . Str::slug($category);

            // Duplicate / re-attach policy on the candidate set sharing the same logical filename
            // for this project, ACROSS ALL document_type folders (auto-classified uploads start in
            // 'Other' and may have already been reclassified into a different folder previously):
            // - existing file with same content (anywhere in the project) => skip as already uploaded
            // - existing row whose stored file is MISSING and same version number => re-attach to
            //   that row using the orphan's existing folder so search keeps finding the same record
            // - same logical filename + different content => create next version in current folder
            $uploadedBase = pathinfo($originalName, PATHINFO_FILENAME);
            $targetKey = DocumentFileVersioning::versionKey($uploadedBase);
            $uploadedVersion = DocumentFileVersioning::extractVersionNumber($uploadedBase);

            $candidates = Document::query()
                ->where('project_id', $targetProject->id)
                ->get(['id', 'file_name', 'file_path', 'document_type', 'entity_id']);

            $uploadedHash = $enableDeepDuplicateCheck ? $this->uploadedFileHash($file) : null;
            if ($uploadedHash !== null) {
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

            $orphanCandidate = null;
            foreach ($candidates as $candidate) {
                $existingBase = pathinfo((string) $candidate->file_name, PATHINFO_FILENAME);
                if (DocumentFileVersioning::versionKey($existingBase) !== $targetKey) {
                    continue;
                }
                if (DocumentFileVersioning::extractVersionNumber($existingBase) !== $uploadedVersion) {
                    continue;
                }
                if ($this->resolveDocumentLocation((string) $candidate->file_path) !== null) {
                    continue;
                }
                $orphanCandidate = $candidate;
                break;
            }

            if ($orphanCandidate !== null) {
                $orphanCategory = (string) ($orphanCandidate->document_type ?: $category);
                $reattachFolder = 'documents/'
                    . Str::slug($targetEntity->name) . '/'
                    . Str::slug($targetProject->project_number) . '/'
                    . Str::slug($orphanCategory);
                $reattachName = (string) $orphanCandidate->file_name;
                try {
                    $reattachPath = $file->storeAs($reattachFolder, $reattachName, $disk);
                } catch (\Throwable $e) {
                    Log::warning('Document re-attach failed: storage write exception', [
                        'disk' => $disk,
                        'document_id' => $orphanCandidate->id,
                        'original_name' => $originalName,
                        'target_path' => $reattachFolder . '/' . $reattachName,
                        'error' => $e->getMessage(),
                    ]);
                    $failedUploads++;
                    continue;
                }

                if (!is_string($reattachPath) || trim($reattachPath) === '') {
                    Log::warning('Document re-attach failed: empty storage path returned', [
                        'disk' => $disk,
                        'document_id' => $orphanCandidate->id,
                        'original_name' => $originalName,
                        'target_path' => $reattachFolder . '/' . $reattachName,
                    ]);
                    $failedUploads++;
                    continue;
                }

                $document = Document::find($orphanCandidate->id);
                if ($document) {
                    $document->entity_id = $targetEntity->id;
                    $document->project_id = $targetProject->id;
                    if ($disciplineName !== null) {
                        $document->discipline = $disciplineName;
                    }
                    // Preserve orphan's document_type. OCR/reclassification will adjust if needed.
                    $document->file_path = $reattachPath;
                    $document->ocr_text = null;
                    $document->modified_by_user_id = Auth::id();
                    $document->save();

                    UserActivityLogger::reattached($document, ['upload_mode' => $uploadMode]);

            try {
                $this->dispatchProcessOcr($document->id);
            } catch (\Throwable $e) {
                \Log::warning('ProcessOCR on re-attach failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            }
                }

                $uploaded++;
                $detectedFolders[$orphanCategory] = true;
                $detectedProjects[$targetProject->project_number] = true;
                continue;
            }

            $storedFileName = DocumentFileVersioning::buildVersionedFilename($originalName, $targetProject->id, $category);
            try {
                $path = $file->storeAs($folderPath, $storedFileName, $disk);
            } catch (\Throwable $e) {
                Log::warning('Document upload failed: storage write exception', [
                    'disk' => $disk,
                    'original_name' => $originalName,
                    'target_path' => $folderPath . '/' . $storedFileName,
                    'error' => $e->getMessage(),
                ]);
                $failedUploads++;
                continue;
            }

            if (!is_string($path) || trim($path) === '') {
                Log::warning('Document upload failed: empty storage path returned', [
                    'disk' => $disk,
                    'original_name' => $originalName,
                    'target_path' => $folderPath . '/' . $storedFileName,
                ]);
                $failedUploads++;
                continue;
            }

            $document = Document::create([
                'entity_id'     => $targetEntity->id,
                'project_id'    => $targetProject->id,
                'discipline'    => $disciplineName,
                'document_type' => $category,
                'file_name'     => $storedFileName,
                'file_path'     => $path,
                'modified_by_user_id' => Auth::id(),
            ]);
            UserActivityLogger::uploaded($document, ['upload_mode' => $uploadMode]);
            // Queue OCR so upload response returns immediately.
            try {
                $this->dispatchProcessOcr($document->id);
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
        if ($failedUploads > 0) {
            $msg .= ' '.$failedUploads.' file(s) failed to upload to storage. Please check server logs and storage credentials.';
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
            'Permit and NOC' => ['Permit and NOC', 'Permit', 'NOC'],
        ];
        $documentTypeFilters = $documentType !== ''
            ? ($documentTypeAliases[$documentType] ?? [$documentType])
            : [];

        // Project options come from Project Master. Always load every project so the
        // entity/project dropdowns can filter client-side when the user switches entity
        // (otherwise only the initially selected entity's projects exist in the DOM).
        $projects = Project::query()
            ->orderBy('project_name');
        $entities = Entity::orderBy('name');
        $user = Auth::user();
        if (! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            $entities->whereIn('id', $entityIds);
            $projects->whereIn('entity_id', $entityIds);
        }
        $projects = $projects->get(['id', 'entity_id', 'project_number', 'project_name']);
        $entities = $entities->get(['id', 'name']);
        $fromMaster = Discipline::orderBy('name')->pluck('name');
        $fromDocuments = Document::whereNotNull('discipline')->where('discipline', '!=', '')
            ->distinct()->orderBy('discipline')->pluck('discipline');
        $disciplines = $fromMaster->merge($fromDocuments)->unique()->sort()->values();
        $documentTypes = Document::whereNotNull('document_type')->where('document_type', '!=', '')
            ->distinct()->orderBy('document_type')->pluck('document_type');

        $documents = null;
        if (!$needsProjectSelection && $hasSearchFilters) {
            if ($entityId && ! $this->access->canAccessEntity($user, $entityId)) {
                abort(403, 'You do not have access to this entity.');
            }
            if ($documentType !== '' && $entityId) {
                $mainFolder = (string) $request->input('main_folder', '');
                if ($mainFolder === '') {
                    $mainFolder = DocumentFilenameParser::mainFolderForDocumentType($documentType) ?? '';
                }
                if (! $this->access->canAccessFolder($user, $entityId, $mainFolder, $documentType)) {
                    abort(403, 'You do not have access to this folder.');
                }
            }

            $query = Document::with(['project', 'entity', 'modifiedBy']);
            $this->access->scopeAccessible($query, $user);

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
                DocumentFilenameParser::applyFolderTypeFilter($query, $documentTypeFilters);
            }

            if ($keyword !== '') {
                // Tokenize on whitespace so multi-word searches like "shop drawing pool"
                // require all words to appear (in any field), but each word is matched
                // as a substring (LIKE) so partial reference numbers still hit.
                $rawTokens = preg_split('/\s+/u', $keyword) ?: [];
                $tokens = [];
                foreach ($rawTokens as $rawToken) {
                    $rawToken = trim((string) $rawToken);
                    if ($rawToken === '') {
                        continue;
                    }
                    $tokens[] = $rawToken;
                }
                if (empty($tokens)) {
                    $tokens = [$keyword];
                }

                foreach ($tokens as $token) {
                    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token) . '%';
                    $query->where(function ($q) use ($like) {
                        $q->whereRaw('LOWER(ocr_text) LIKE LOWER(?)', [$like])
                          ->orWhereRaw('LOWER(file_name) LIKE LOWER(?)', [$like])
                          ->orWhereRaw('LOWER(document_type) LIKE LOWER(?)', [$like])
                          ->orWhereRaw('LOWER(COALESCE(discipline, \'\')) LIKE LOWER(?)', [$like])
                          ->orWhereHas('entity', fn ($eq) => $eq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                          ->orWhereHas('project', fn ($pq) => $pq
                              ->whereRaw('LOWER(project_number) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(project_name) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(COALESCE(client_name, \'\')) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(COALESCE(consultant, \'\')) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(COALESCE(project_manager, \'\')) LIKE LOWER(?)', [$like])
                              ->orWhereRaw('LOWER(COALESCE(document_controller, \'\')) LIKE LOWER(?)', [$like]));
                    });
                }
            }

            $documents = $query->paginate(10)->withQueryString();
            $collection = $documents->getCollection();
            $missingModifierIds = $collection
                ->filter(fn (Document $document) => $document->modified_by_user_id === null)
                ->pluck('id');

            $activityUsers = collect();
            if ($missingModifierIds->isNotEmpty()) {
                $activityUsers = UserActivity::query()
                    ->whereIn('document_id', $missingModifierIds)
                    ->whereIn('action', [
                        UserActivity::ACTION_UPLOADED,
                        UserActivity::ACTION_REPLACED,
                        UserActivity::ACTION_REATTACHED,
                    ])
                    ->with('user:id,name,username')
                    ->orderByDesc('created_at')
                    ->get()
                    ->groupBy('document_id')
                    ->map(fn ($group) => $group->first()?->user);
            }

            $collection->transform(function (Document $document) use ($activityUsers) {
                $document->file_available = $this->resolveDocumentLocation((string) $document->file_path) !== null;

                if ($document->modifiedBy === null && $activityUsers->has($document->id)) {
                    $document->setRelation('modifiedBy', $activityUsers->get($document->id));
                }

                return $document;
            });
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

    public function edit(Request $request, int $id)
    {
        $document = Document::with(['project', 'entity'])->find($id);

        if (! $document) {
            abort(404, 'Document not found.');
        }

        $this->authorizeDocument($document);

        $fileAvailable = $this->resolveDocumentLocation((string) $document->file_path) !== null;
        $previewUrl = $fileAvailable ? DocumentPreviewUrl::inlineUrl($document) : null;
        $fileSizeBytes = $fileAvailable ? DocumentPreviewUrl::fileSizeBytes($document) : null;

        $isPdf = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)) === 'pdf';

        $onlyOffice = app(OnlyOfficeService::class);
        $onlyOfficeServerUrl = $onlyOffice->serverUrl();
        $onlyOfficeReachable = $onlyOffice->isEnabled() && $onlyOffice->isReachable();
        // OnlyOffice PDF editing is unreliable on many files; PDFs use download → edit → save-version instead.
        $onlyOfficeEnabled = $onlyOfficeReachable
            && $fileAvailable
            && ! $isPdf
            && $onlyOffice->supportsFile($document->file_name);
        $onlyOfficeConfig = $onlyOfficeEnabled
            ? $onlyOffice->editorConfig($document, $request->user())
            : null;
        $nextVersionName = DocumentFileVersioning::buildNextEditVersionFilename(
            $document->file_name,
            (int) $document->project_id,
            (string) ($document->document_type ?: 'Other')
        );

        return view('documents.edit', [
            'document' => $document,
            'fileAvailable' => $fileAvailable,
            'isPdf' => $isPdf,
            'previewUrl' => $previewUrl,
            'downloadUrl' => route('documents.download', ['id' => $document->id]),
            'fileSizeMb' => $fileSizeBytes !== null ? round($fileSizeBytes / 1024 / 1024, 1) : null,
            'onlyOfficeEnabled' => $onlyOfficeEnabled,
            'onlyOfficeReachable' => $onlyOfficeReachable,
            'onlyOfficeConfigured' => $onlyOffice->isEnabled(),
            'onlyOfficeServerUrl' => $onlyOfficeServerUrl,
            'onlyOfficeConfig' => $onlyOfficeConfig,
            'nextVersionName' => $nextVersionName,
            'pdfEditorUrl' => route('documents.download', ['id' => $document->id]),
        ]);
    }

    public function versionSaveStatus(int $id)
    {
        $document = Document::find($id);
        if ($document === null) {
            abort(404);
        }

        $this->authorizeDocument($document);

        $payload = \Illuminate\Support\Facades\Cache::get('doc_version_saved_from_'.$id);
        if (! is_array($payload)) {
            return response()->json(['saved' => false]);
        }

        return response()->json([
            'saved' => true,
            'new_document_id' => (int) ($payload['new_document_id'] ?? 0),
            'new_file_name' => (string) ($payload['new_file_name'] ?? ''),
        ]);
    }

    /**
     * Save uploaded edits as a new version (V1, V2, …). Prior versions are kept.
     */
    public function replace(Request $request, int $id)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $document = Document::find($id);

        if (! $document) {
            abort(404, 'Document not found.');
        }

        $this->authorizeDocument($document);

        $maxFileMb = max(1, (int) env('DOC_UPLOAD_MAX_FILE_MB', 1024));
        $maxFileKb = $maxFileMb * 1024;

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.$maxFileKb,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('Invalid file upload.');

                        return;
                    }

                    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
                    $originalExt = strtolower((string) $value->getClientOriginalExtension());
                    $guessedExt = strtolower((string) $value->guessExtension());

                    if (in_array($originalExt, $allowed, true) || in_array($guessedExt, $allowed, true)) {
                        return;
                    }

                    $fail('The file must be one of: pdf, doc, docx, xls, xlsx.');
                },
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $newDocument = (new DocumentVersionSaver)->saveFromUpload($document, $file);
        } catch (\Throwable $e) {
            Log::warning('Document version save failed', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        $returnUrl = $request->input('return_url');
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'new_document_id' => $newDocument->id,
                'new_file_name' => $newDocument->file_name,
                'message' => 'Saved as '.$newDocument->file_name,
            ]);
        }

        if (is_string($returnUrl) && $returnUrl !== '' && str_starts_with($returnUrl, url('/'))) {
            return redirect($returnUrl)->with('success', 'Saved as '.$newDocument->file_name.'. Search text will refresh after OCR completes.');
        }

        return redirect()
            ->route('documents.edit', ['id' => $newDocument->id])
            ->with('success', 'Saved as '.$newDocument->file_name.'. Search text will refresh after OCR completes.');
    }

    public function download(int $id)
    {
        $document = Document::find($id);

        if (!$document) {
            Log::warning('Document download failed: missing database row', [
                'document_id' => $id,
                'url' => request()->fullUrl(),
                'host' => request()->getHost(),
            ]);
            abort(404, 'Document not found. Use Search to find a file and click Download from the result.');
        }

        $this->authorizeDocument($document);

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);

        if ($location === null) {
            Log::warning('Document download failed: file path not found', [
                'document_id' => $id,
                'stored_path' => $path,
                'url' => request()->fullUrl(),
                'host' => request()->getHost(),
            ]);
            abort(404, 'File not found on disk: ' . $path);
        }

        $mimeType = $this->detectMimeType($location, $document->file_name);
        if ($location['source'] === 'disk') {
            return Storage::disk($location['disk'])->download($location['path'], $document->file_name, [
                'Content-Type' => $mimeType,
            ]);
        }

        return response()->download($location['path'], $document->file_name, [
            'Content-Type' => $mimeType,
        ]);
    }

    /** Serve file with inline disposition where browser supports preview. */
    public function previewUrl(int $id)
    {
        $document = Document::find($id);

        if (! $document) {
            abort(404, 'Document not found.');
        }

        $this->authorizeDocument($document);

        if ($this->resolveDocumentLocation((string) $document->file_path) === null) {
            abort(404, 'File not found on disk.');
        }

        $url = DocumentPreviewUrl::inlineUrl($document);
        if ($url === null) {
            abort(404, 'Preview unavailable.');
        }

        return response()->json(['url' => $url]);
    }

    /** Serve file with inline disposition where browser supports preview. */
    public function viewPdf(int $id)
    {
        $document = Document::find($id);

        if (!$document) {
            Log::warning('Document view failed: missing database row', [
                'document_id' => $id,
                'url' => request()->fullUrl(),
                'host' => request()->getHost(),
            ]);
            abort(404, 'Document not found.');
        }

        $this->authorizeDocument($document);

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);

        if ($location === null) {
            Log::warning('Document view failed: file path not found', [
                'document_id' => $id,
                'stored_path' => $path,
                'url' => request()->fullUrl(),
                'host' => request()->getHost(),
            ]);
            abort(404, 'File not found on disk: ' . $path);
        }

        $presigned = DocumentPreviewUrl::presignedRedirectUrl($document);
        if ($presigned !== null && ! request()->boolean('proxy')) {
            return redirect()->away($presigned);
        }

        $mimeType = $this->detectMimeType($location, $document->file_name);
        if ($location['source'] === 'disk') {
            return Storage::disk($location['disk'])->response(
                $location['path'],
                $document->file_name,
                ['Content-Type' => $mimeType],
                'inline'
            );
        }

        return response()->file(
            $location['path'],
            ['Content-Type' => $mimeType]
        );
    }

    public function destroy(int $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return back()->with('success', 'File not found');
        }

        $this->authorizeDocument($document);

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);
        if ($location !== null) {
            if ($location['source'] === 'disk') {
                Storage::disk($location['disk'])->delete($location['path']);
            } else {
                @unlink($location['path']);
            }
        }

        UserActivityLogger::deleted($document);
        $document->delete();

        return back()->with('success', 'File successfully deleted');
    }

    protected function shareErrorResponse(Request $request, int $id, string $message, ?Document $document = null)
    {
        return back()
            ->withErrors(['share_email_' . $id => $message])
            ->withInput($request->only('email', 'message'))
            ->with('share_context', [
                'id' => $id,
                'file_name' => $document?->file_name ?? 'Document',
                'project_number' => $document?->project?->project_number ?? '',
            ]);
    }

    public function shareEmailSuggestions(Request $request, MicrosoftGraphPeopleService $people)
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $user = $request->user();
        if ($user === null) {
            return response()->json(['suggestions' => []], 401);
        }

        return response()->json([
            'suggestions' => $people->search($user, $query),
        ]);
    }

    public function share(Request $request, int $id, MicrosoftGraphMailService $graphMail)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $email = trim((string) $validated['email']);
        $personalMessage = trim((string) ($validated['message'] ?? ''));

        $sender = $request->user();
        if ($sender === null) {
            abort(403);
        }

        if (! $graphMail->canSendAsUser($sender)) {
            return redirect()->route('login.microsoft.mail')->withErrors([
                'share_email_' . $id => 'Allow Microsoft mail access first, then try Share again.',
            ]);
        }

        $document = Document::with(['entity', 'project'])->find($id);
        if (!$document) {
            return $this->shareErrorResponse($request, $id, 'Document not found.');
        }

        $this->authorizeDocument($document);

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);
        if ($location === null) {
            return $this->shareErrorResponse($request, $id, 'File not found in storage.', $document);
        }

        try {
            $meta = DocumentFilenameParser::extractReferenceAndSubject($document->ocr_text, $document->file_name);
            $referenceNo = $meta['reference_no'] ?? null;
            $documentSubject = $meta['subject'] ?? null;
            $projectNumber = (string) ($document->project?->project_number ?? '');
            $projectName = (string) ($document->project?->project_name ?? '');
            $entityName = (string) ($document->entity?->name ?? '');
            $folderLabel = DocumentFilenameParser::folderDisplayLabel($document->document_type, $document->file_name, $document->ocr_text);

            $fromAddress = (string) $sender->email;
            $fromName = (string) ($sender->name ?: $sender->username ?: $sender->email);
            $subject = SharedDocumentMailBuilder::subject($document->file_name, $referenceNo, $projectNumber);

            SendSharedDocumentEmail::dispatchSync(
                documentId: $id,
                senderUserId: $sender->id,
                recipient: $email,
                subject: $subject,
                fileName: $document->file_name,
                fromAddress: $fromAddress,
                fromName: $fromName,
                mailData: [
                    'referenceNo' => $referenceNo,
                    'documentSubject' => $documentSubject,
                    'projectNumber' => $projectNumber,
                    'projectName' => $projectName,
                    'entityName' => $entityName,
                    'folderLabel' => $folderLabel,
                    'personalMessage' => $personalMessage,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Document share failed', [
                'document_id' => $id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            $message = config('app.debug')
                ? 'Could not send email: '.$e->getMessage()
                : 'Could not send email. Check mail settings and try again.';

            return $this->shareErrorResponse($request, $id, $message, $document);
        }

        return back()->with('success', 'Email sent successfully.');
    }

    protected function detectMimeType(array $location, string $fileName): string
    {
        try {
            if (($location['source'] ?? '') === 'disk') {
                $mime = Storage::disk($location['disk'])->mimeType($location['path']);
                if (is_string($mime) && trim($mime) !== '') {
                    return $mime;
                }
            } elseif (($location['source'] ?? '') === 'file') {
                $mime = @mime_content_type($location['path']);
                if (is_string($mime) && trim($mime) !== '') {
                    return $mime;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Mime type detect failed', ['error' => $e->getMessage(), 'file_name' => $fileName]);
        }

        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    /**
     * Resolve a document path across the configured disk and legacy local paths.
     *
     * @return array{source:'disk',disk:string,path:string}|array{source:'file',path:string}|null
     */
    protected function resolveDocumentLocation(string $path): ?array
    {
        return DocumentLocationResolver::resolve($path);
    }

    /**
     * Run first-pass OCR in-process when the queue is "sync" or DMS_OCR_SYNC_ON_UPLOAD=true,
     * so local installs without queue:work still get ocr_text and reclassification.
     * Otherwise queue after the HTTP response.
     */
    protected function dispatchProcessOcr(int $documentId): void
    {
        $inline = config('queue.default') === 'sync'
            || filter_var(env('DMS_OCR_SYNC_ON_UPLOAD', false), FILTER_VALIDATE_BOOL);
        if ($inline) {
            (new ProcessOCR($documentId))->handle();

            return;
        }
        ProcessOCR::dispatch($documentId)->afterResponse();
    }

    /**
     * Best-effort folder prediction during upload.
     * Keep this lightweight (filename-only) so upload request returns quickly.
     * OCR-based refinement still runs asynchronously via ProcessOCR after upload.
     *
     * @param  array<int, string>  $validSubfolders
     */
    protected function predictUploadCategory($file, string $originalName, array $validSubfolders): string
    {
        $result = DocumentFilenameParser::classifyForAutomation($originalName, null);
        $predicted = trim((string) ($result['document_category'] ?? 'Other'));
        if ($predicted === '' || !in_array($predicted, $validSubfolders, true)) {
            return 'Other';
        }

        return $predicted;
    }
}