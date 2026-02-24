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
        $documentCategories = static::documentCategories();
        return view('documents.upload', compact('entities', 'projects', 'documentCategories'));
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
            'document_category' => 'required|string|max:100',
            'documents'         => 'required',
            'documents.*'       => 'file|mimes:pdf|max:51200',
        ]);

        $category = trim($request->document_category);
        if (!in_array($category, static::documentCategories(), true)) {
            return back()->withErrors(['document_category' => 'Invalid document category.'])->withInput();
        }

        $entity = Entity::findOrFail($request->entity_id);
        $project = Project::where('id', $request->project_id)->where('entity_id', $entity->id)->firstOrFail();

        $folderPath = 'documents/'
            . Str::slug($entity->name) . '/'
            . Str::slug($project->project_number) . '/'
            . Str::slug($category);

        $uploaded = 0;
        foreach ($request->file('documents') as $file) {
            $originalName = $file->getClientOriginalName();
            $path = $file->storeAs($folderPath, $originalName);

            $document = Document::create([
                'entity_id'     => $entity->id,
                'project_id'    => $project->id,
                'discipline'    => null,
                'document_type' => $category,
                'file_name'     => $originalName,
                'file_path'     => $path,
            ]);
            ProcessOCR::dispatch($document->id);
            $uploaded++;
        }

        return back()->with('success', $uploaded . ' file(s) uploaded to ' . $entity->name . ' / ' . $project->project_number . ' / ' . $category . '. OCR running in background.');
    }

    public function search(Request $request)
    {
        $keyword = trim($request->keyword ?? '');
        $projectId = $request->project_id ? (int) $request->project_id : null;
        $entityId = $request->entity_id ? (int) $request->entity_id : null;
        $discipline = trim($request->discipline ?? '');
        $documentType = trim($request->document_type ?? '');

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
        if ($documentType !== '') {
            $query->where('document_type', $documentType);
        }

        if ($keyword !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
            $driver = DB::connection()->getDriverName();
            // Search in: first-page content (ocr_text), file name, folder names (entity, project number, project name, document type). Case-insensitive.
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

        // Options for folder / predict dropdowns (only existing values)
        $projects = Project::orderBy('project_name')->get(['id', 'project_number', 'project_name']);
        $entities = Entity::orderBy('name')->get(['id', 'name']);
        $disciplines = Document::whereNotNull('discipline')->where('discipline', '!=', '')
            ->distinct()->orderBy('discipline')->pluck('discipline');
        $documentTypes = Document::whereNotNull('document_type')->where('document_type', '!=', '')
            ->distinct()->orderBy('document_type')->pluck('document_type');

        $totalDocuments = Document::count();
        $documentsWithoutOcr = Document::where(function ($q) {
            $q->whereNull('ocr_text')->orWhere('ocr_text', '');
        })->count();

        return view('documents.search', compact(
            'documents', 'keyword', 'projects', 'entities',
            'disciplines', 'documentTypes', 'totalDocuments', 'documentsWithoutOcr'
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
}