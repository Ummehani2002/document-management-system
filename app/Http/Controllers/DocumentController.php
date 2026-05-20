<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Jobs\ProcessOCR;
use App\Jobs\SendSharedDocumentEmail;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentFileReplacer;
use App\Services\DocumentFileVersioning;
use App\Services\DocumentLocationResolver;
use App\Services\DocumentPreviewUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\UploadedFile;

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

    public function create(Request $request)
    {
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $projects = Project::with('entity:id,name')->orderBy('project_number')->get();
        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $disciplines = Discipline::orderBy('name')->get(['id', 'name']);
        $mode = (string) $request->query('mode', old('upload_mode', ''));
        if (!in_array($mode, ['auto', 'manual'], true)) {
            $mode = '';
        }

        $directUploadEnabled = config('filesystems.default') === 's3';
        $directUploadMinMb = max(1, (int) env('DOC_DIRECT_UPLOAD_MIN_MB', 75));

        return view('documents.upload', compact('entities', 'projects', 'folderTree', 'mode', 'disciplines', 'directUploadEnabled', 'directUploadMinMb'));
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
                    $document->save();

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
            ]);
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
            ->orderBy('project_name')
            ->get(['id', 'entity_id', 'project_number', 'project_name']);
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $fromMaster = Discipline::orderBy('name')->pluck('name');
        $fromDocuments = Document::whereNotNull('discipline')->where('discipline', '!=', '')
            ->distinct()->orderBy('discipline')->pluck('discipline');
        $disciplines = $fromMaster->merge($fromDocuments)->unique()->sort()->values();
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
            $documents->getCollection()->transform(function (Document $document) {
                $document->file_available = $this->resolveDocumentLocation((string) $document->file_path) !== null;

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

    public function edit(int $id)
    {
        $document = Document::with(['project', 'entity'])->find($id);

        if (! $document) {
            abort(404, 'Document not found.');
        }

        $fileAvailable = $this->resolveDocumentLocation((string) $document->file_path) !== null;
        $previewUrl = $fileAvailable ? DocumentPreviewUrl::inlineUrl($document) : null;
        $fileSizeBytes = $fileAvailable ? DocumentPreviewUrl::fileSizeBytes($document) : null;

        return view('documents.edit', [
            'document' => $document,
            'fileAvailable' => $fileAvailable,
            'previewUrl' => $previewUrl,
            'fileSizeMb' => $fileSizeBytes !== null ? round($fileSizeBytes / 1024 / 1024, 1) : null,
        ]);
    }

    /**
     * Replace the stored file for an existing document (same record, same storage path).
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
            (new DocumentFileReplacer)->replace($document, $file);
        } catch (\Throwable $e) {
            Log::warning('Document replace failed', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        $returnUrl = $request->input('return_url');
        if (is_string($returnUrl) && $returnUrl !== '' && str_starts_with($returnUrl, url('/'))) {
            return redirect($returnUrl)->with('success', 'File replaced successfully. Search text will refresh after OCR completes.');
        }

        return redirect()
            ->route('documents.edit', ['id' => $id])
            ->with('success', 'File replaced successfully. Search text will refresh after OCR completes.');
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
        if ($presigned !== null) {
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

    public function share(Request $request, int $id)
    {
        $email = trim((string) $request->input('email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors([
                'share_email_' . $id => 'Enter a valid email address.',
            ])->withInput();
        }

        $document = Document::find($id);
        if (!$document) {
            return back()->withErrors([
                'share_email_' . $id => 'Document not found.',
            ])->withInput();
        }

        $path = (string) $document->file_path;
        $location = $this->resolveDocumentLocation($path);
        if ($location === null) {
            return back()->withErrors([
                'share_email_' . $id => 'File not found in storage.',
            ])->withInput();
        }

        try {
            if ($location['source'] === 'disk') {
                $fileBytes = Storage::disk($location['disk'])->get($location['path']);
            } else {
                $fileBytes = @file_get_contents($location['path']);
                if ($fileBytes === false) {
                    throw new \RuntimeException('Unable to read file content.');
                }
            }

            $recipient = $email;
            $subject = 'Shared file: ' . $document->file_name;
            $mimeType = $this->detectMimeType($location, $document->file_name);
            $fromAddress = (string) config('mail.from.address');
            $fromName = (string) config('mail.from.name');

            SendSharedDocumentEmail::dispatch(
                documentId: $id,
                recipient: $recipient,
                subject: $subject,
                fileName: $document->file_name,
                fileBytes: $fileBytes,
                mimeType: $mimeType,
                projectNumber: (string) ($document->project?->project_number ?? '-'),
                fromAddress: $fromAddress,
                fromName: $fromName
            )->onQueue('emails');
        } catch (\Throwable $e) {
            Log::warning('Document share failed', [
                'document_id' => $id,
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'share_email_' . $id => 'Could not send email. Check mail settings and try again.',
            ])->withInput();
        }

        return back()->with('success', 'PDF is being sent to ' . $request->input('email') . '.');
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