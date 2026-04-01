<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Jobs\ProcessOCR;
use App\Services\DocumentFilenameParser;
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
        $suggestion = DocumentFilenameParser::suggestPlacement($filename);
        return response()->json($suggestion);
    }

    public function store(Request $request)
    {
        $request->validate([
            'entity_id'         => 'required|exists:entities,id',
            'project_id'        => 'required|exists:projects,id',
            'documents'         => 'required',
            'documents.*'       => 'file|mimes:pdf|max:51200',
        ]);

        $entity = Entity::findOrFail($request->entity_id);
        $project = Project::where('id', $request->project_id)->where('entity_id', $entity->id)->firstOrFail();

        $uploaded = 0;
        $detectedFolders = [];
        $detectedProjects = [];
        foreach ($request->file('documents') as $file) {
            $originalName = $file->getClientOriginalName();
            $suggestedPlacement = DocumentFilenameParser::suggestPlacement($originalName);
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

            $storedFileName = $this->buildVersionedFilename($originalName, $targetProject->id, $category);
            $path = $file->storeAs($folderPath, $storedFileName);

            $document = Document::create([
                'entity_id'     => $targetEntity->id,
                'project_id'    => $targetProject->id,
                'discipline'    => null,
                'document_type' => $category,
                'file_name'     => $storedFileName,
                'file_path'     => $path,
            ]);
            // Run OCR synchronously so first-page content is indexed immediately (works in production without a queue worker)
            try {
                (new ProcessOCR($document->id))->handle();
            } catch (\Throwable $e) {
                \Log::warning('ProcessOCR on upload failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            }
            $uploaded++;
            $detectedFolders[$category] = true;
            $detectedProjects[$targetProject->project_number] = true;
        }

        return back()->with('success', 'File successfully uploaded');
    }

    protected function buildVersionedFilename(string $originalName, int $projectId, string $category): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        $targetKey = $this->versionKey($nameWithoutExt);

        $existingNames = Document::query()
            ->where('project_id', $projectId)
            ->where('document_type', $category)
            ->pluck('file_name');

        $maxVersion = -1;
        foreach ($existingNames as $existingName) {
            $existingBase = pathinfo((string) $existingName, PATHINFO_FILENAME);
            if ($this->versionKey($existingBase) !== $targetKey) {
                continue;
            }
            $maxVersion = max($maxVersion, $this->extractVersionNumber($existingBase));
        }

        // No match found in this project/folder: keep original filename as first version.
        if ($maxVersion < 0) {
            return $originalName;
        }

        $nextVersion = $maxVersion + 1;
        $nextBase = $this->injectVersionNumber($nameWithoutExt, $nextVersion);

        return $extension !== '' ? ($nextBase . '.' . $extension) : $nextBase;
    }

    protected function versionKey(string $baseName): string
    {
        $normalized = strtoupper($baseName);
        $normalized = preg_replace('/\bR\s*NO\.?\s*[-.:]?\s*\d+\b/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bR(?:EV(?:ISION)?)?\s*[-.:]?\s*\d+\b/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bV(?:ERSION)?\s*[-.:]?\s*\d+\b/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        return trim($normalized);
    }

    protected function extractVersionNumber(string $baseName): int
    {
        if (preg_match('/\bR\s*NO\.?\s*[-.:]?\s*(\d+)\b/ui', $baseName, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bR(?:EV(?:ISION)?)?\s*[-.:]?\s*(\d+)\b/ui', $baseName, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bV(?:ERSION)?\s*[-.:]?\s*(\d+)\b/ui', $baseName, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    protected function injectVersionNumber(string $baseName, int $version): string
    {
        if (preg_match('/\bR\s*NO\.?\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bR\s*NO\.?\s*[-.:]?\s*\d+\b/ui', 'R NO ' . $version, $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bR\.\d+\b/ui', $baseName)) {
            return preg_replace('/\bR\.\d+\b/ui', 'R.' . str_pad((string) $version, 2, '0', STR_PAD_LEFT), $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bREV(?:ISION)?\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bREV(?:ISION)?\s*[-.:]?\s*\d+\b/ui', 'REV-' . str_pad((string) $version, 2, '0', STR_PAD_LEFT), $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bR\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bR\s*[-.:]?\s*\d+\b/ui', 'R.' . str_pad((string) $version, 2, '0', STR_PAD_LEFT), $baseName, 1) ?? $baseName;
        }
        if (preg_match('/\bV(?:ERSION)?\s*[-.:]?\s*\d+\b/ui', $baseName)) {
            return preg_replace('/\bV(?:ERSION)?\s*[-.:]?\s*\d+\b/ui', 'Version ' . $version, $baseName, 1) ?? $baseName;
        }

        return $baseName . ' - Version ' . $version;
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
                          ->orWhereHas('project', fn ($pq) => $pq->whereRaw('LOWER(project_number) LIKE LOWER(?)', [$like])->orWhereRaw('LOWER(project_name) LIKE LOWER(?)', [$like]));
                    } else {
                        $q->whereRaw('LOWER(ocr_text) LIKE LOWER(?)', [$like])
                          ->orWhereRaw('LOWER(file_name) LIKE LOWER(?)', [$like])
                          ->orWhere('document_type', 'like', $like)
                          ->orWhereHas('entity', fn ($eq) => $eq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]))
                          ->orWhereHas('project', fn ($pq) => $pq->whereRaw('LOWER(project_number) LIKE LOWER(?)', [$like])->orWhereRaw('LOWER(project_name) LIKE LOWER(?)', [$like]));
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

        $path = $document->file_path;
        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found on disk: ' . $path);
        }

        return Storage::disk($disk)->download($path, $document->file_name, [
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

        $path = $document->file_path;
        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found on disk: ' . $path);
        }

        return Storage::disk($disk)->response($path, $document->file_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy(int $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return back()->with('success', 'File not found');
        }

        $path = $document->file_path;
        $disk = config('filesystems.default');

        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $document->delete();

        return back()->with('success', 'File successfully deleted');
    }
}