<?php

namespace App\Services;

use App\Jobs\ProcessOCR;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\UserActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentVersionSaver
{
    /**
     * Save edited file as a new document version (V1, V2, …). Prior versions are kept.
     */
    public function saveFromUpload(Document $source, UploadedFile $file): Document
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        return $this->saveFromContents($source, $contents);
    }

    public function saveFromContents(Document $source, string $contents): Document
    {
        $source->loadMissing(['entity', 'project']);

        $entity = $source->entity;
        $project = $source->project;
        if ($entity === null || $project === null) {
            throw new \RuntimeException('Document is missing entity or project.');
        }

        $category = (string) ($source->document_type ?: 'Other');
        $newFileName = DocumentFileVersioning::buildNextEditVersionFilename(
            $source->file_name,
            (int) $source->project_id,
            $category
        );

        $folderPath = $this->folderPath($entity, $project, $category);
        $disk = (string) config('filesystems.default', 'local');
        $storedPath = $folderPath.'/'.$newFileName;

        if (! Storage::disk($disk)->put($storedPath, $contents)) {
            throw new \RuntimeException('Could not write the new version to storage.');
        }

        $newDocument = Document::create([
            'entity_id' => $source->entity_id,
            'project_id' => $source->project_id,
            'discipline' => $source->discipline,
            'document_type' => $source->document_type,
            'file_name' => $newFileName,
            'file_path' => $storedPath,
            'ocr_text' => null,
            'modified_by_user_id' => Auth::id(),
        ]);

        UserActivityLogger::log(UserActivity::ACTION_REPLACED, $newDocument, [
            'versioned_from_id' => $source->id,
            'versioned_from_name' => $source->file_name,
            'saved_as_version' => $newFileName,
        ]);
        $this->dispatchProcessOcr($newDocument->id);

        return $newDocument;
    }

    protected function folderPath(Entity $entity, Project $project, string $category): string
    {
        return 'documents/'
            .Str::slug($entity->name).'/'
            .Str::slug($project->project_number).'/'
            .Str::slug($category);
    }

    protected function dispatchProcessOcr(int $documentId): void
    {
        $inline = config('queue.default') === 'sync'
            || filter_var(env('DMS_OCR_SYNC_ON_UPLOAD', false), FILTER_VALIDATE_BOOL);

        try {
            if ($inline) {
                (new ProcessOCR($documentId))->handle();

                return;
            }
            ProcessOCR::dispatch($documentId)->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('ProcessOCR after version save failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
