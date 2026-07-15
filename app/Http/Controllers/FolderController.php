<?php

namespace App\Http\Controllers;

use App\Models\DocumentMainFolder;
use App\Models\DocumentSubfolder;
use App\Services\DocumentFolderCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FolderController extends Controller
{
    public function index(): View
    {
        $folders = DocumentMainFolder::query()
            ->with(['subfolders'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('folders.index', compact('folders'));
    }

    public function create(): View
    {
        return view('folders.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:document_main_folders,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        DocumentMainFolder::create([
            'name' => trim($validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        DocumentFolderCatalog::clearCache();

        return redirect()
            ->route('folders.index')
            ->with('success', 'Main folder created.');
    }

    public function edit(DocumentMainFolder $folder): View
    {
        return view('folders.edit', compact('folder'));
    }

    public function update(Request $request, DocumentMainFolder $folder): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:document_main_folders,name,'.$folder->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $newName = trim($validated['name']);
        $sortOrder = (int) ($validated['sort_order'] ?? $folder->sort_order);

        if ($folder->name !== $newName) {
            DocumentFolderCatalog::renameMainFolder($folder, $newName);
        }

        $folder->refresh();
        $folder->update(['sort_order' => $sortOrder]);
        DocumentFolderCatalog::clearCache();

        return redirect()
            ->route('folders.index')
            ->with('success', 'Main folder updated.');
    }

    public function destroy(DocumentMainFolder $folder): RedirectResponse
    {
        if ($folder->subfolders()->exists()) {
            return back()->withErrors([
                'folder' => 'Remove all subfolders before deleting this main folder.',
            ]);
        }

        $folder->delete();
        DocumentFolderCatalog::clearCache();

        return redirect()
            ->route('folders.index')
            ->with('success', 'Main folder deleted.');
    }

    public function createSubfolder(DocumentMainFolder $folder): View
    {
        return view('folders.subfolders.create', compact('folder'));
    }

    public function storeSubfolder(Request $request, DocumentMainFolder $folder): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:document_subfolders,name,NULL,id,main_folder_id,'.$folder->id,
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $folder->subfolders()->create([
            'name' => trim($validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        DocumentFolderCatalog::clearCache();

        return redirect()
            ->route('folders.index')
            ->with('success', 'Subfolder added under '.$folder->name.'.');
    }

    public function editSubfolder(DocumentSubfolder $subfolder): View
    {
        $subfolder->load('mainFolder');

        return view('folders.subfolders.edit', compact('subfolder'));
    }

    public function updateSubfolder(Request $request, DocumentSubfolder $subfolder): RedirectResponse
    {
        $validated = $request->validate([
            'main_folder_id' => ['required', 'integer', 'exists:document_main_folders,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:document_subfolders,name,'.$subfolder->id.',id,main_folder_id,'.(int) $request->input('main_folder_id'),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $newName = trim($validated['name']);
        $newMainId = (int) $validated['main_folder_id'];
        $sortOrder = (int) ($validated['sort_order'] ?? $subfolder->sort_order);

        if ($subfolder->name !== $newName) {
            DocumentFolderCatalog::renameSubfolder($subfolder, $newName);
        }

        $subfolder->refresh();
        $subfolder->update([
            'main_folder_id' => $newMainId,
            'sort_order' => $sortOrder,
        ]);
        DocumentFolderCatalog::clearCache();

        return redirect()
            ->route('folders.index')
            ->with('success', 'Subfolder updated.');
    }

    public function destroySubfolder(DocumentSubfolder $subfolder): RedirectResponse
    {
        $docCount = $subfolder->name !== ''
            ? \App\Models\Document::query()->where('document_type', $subfolder->name)->count()
            : 0;

        if ($docCount > 0) {
            return back()->withErrors([
                'subfolder' => "Cannot delete “{$subfolder->name}”: {$docCount} document(s) still use this folder.",
            ]);
        }

        $subfolder->delete();
        DocumentFolderCatalog::clearCache();

        return redirect()
            ->route('folders.index')
            ->with('success', 'Subfolder deleted.');
    }
}
