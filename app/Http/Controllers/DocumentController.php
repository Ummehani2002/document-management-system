<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Jobs\ProcessOCR;
use App\Jobs\SendSharedDocumentEmail;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentFileVersioning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

        return view('documents.upload', compact('entities', 'projects', 'folderTree', 'mode', 'disciplines'));
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
            'documents.*'       => 'file|mimes:pdf,doc,docx,xls,xlsx|max:' . $maxFileKb,
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
        $disk = config('filesystems.default');
        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $targetEntity = $entity;
            $targetProject = $project;
            $category = 'Other';

            if ($uploadMode === 'manual') {
                $category = $manualSubfolder;
            } else {
                // In auto mode, classify after OCR (ProcessOCR + DocumentReclassificationService).
                // Initial upload goes to Other; OCR will move it to the right folder.
                $category = 'Other';
            }

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
        $rawPath = trim($path);
        if ($rawPath === '') {
            return null;
        }

        $rawPath = str_replace('\\', '/', $rawPath);

        // Some legacy records already store full absolute paths.
        if (is_file($rawPath)) {
            return ['source' => 'file', 'path' => $rawPath];
        }

        $normalizedPath = ltrim($rawPath, '/');
        if ($normalizedPath === '') {
            return null;
        }

        // Build candidate relative paths to tolerate legacy prefixes.
        $relativeCandidates = [];
        $relativeCandidates[] = $normalizedPath;
        foreach (['storage/', 'app/', 'app/private/', 'app/public/', 'public/', 'private/'] as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                $relativeCandidates[] = substr($normalizedPath, strlen($prefix));
            }
        }
        $relativeCandidates = array_values(array_unique(array_filter($relativeCandidates)));

        $candidateDisks = array_values(array_unique(array_filter([
            config('filesystems.default'),
            'local',
            'public',
        ])));

        foreach ($candidateDisks as $disk) {
            foreach ($relativeCandidates as $candidatePath) {
                try {
                    if (Storage::disk($disk)->exists($candidatePath)) {
                        return ['source' => 'disk', 'disk' => $disk, 'path' => $candidatePath];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Document lookup disk probe failed', [
                        'disk' => $disk,
                        'path' => $candidatePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $absoluteCandidates = [];
        foreach ($relativeCandidates as $candidatePath) {
            $absoluteCandidates[] = storage_path('app/' . $candidatePath);
            $absoluteCandidates[] = storage_path('app/private/' . $candidatePath);
            $absoluteCandidates[] = storage_path('app/public/' . $candidatePath);
            $absoluteCandidates[] = public_path($candidatePath);
            $absoluteCandidates[] = base_path($candidatePath);
        }
        $absoluteCandidates = array_values(array_unique($absoluteCandidates));

        foreach ($absoluteCandidates as $absolutePath) {
            if (is_file($absolutePath)) {
                return ['source' => 'file', 'path' => $absolutePath];
            }
        }

        return null;
    }
}