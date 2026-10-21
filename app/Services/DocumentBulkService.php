<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Multi-select actions from the document list: move, copy and ZIP download.
 * Files are copied with read/write streams so it works on local disks and R2/S3
 * (R2 rejects the ACL calls Flysystem's native copy makes).
 */
class DocumentBulkService
{
    /**
     * Move a document into another entity / project / folder. The chosen folder is locked.
     */
    public function move(Document $document, Entity $entity, Project $project, string $documentType): bool
    {
        $location = DocumentLocationResolver::resolve((string) $document->file_path);
        if ($location === null) {
            return false;
        }

        $disk = config('filesystems.default');
        $targetPath = $this->uniqueTargetPath($disk, $entity, $project, $documentType, (string) $document->file_name);

        if (! ($location['source'] === 'disk' && $location['disk'] === $disk && $location['path'] === $targetPath)) {
            if (! $this->copyStoredFile($location, $disk, $targetPath)) {
                return false;
            }
            $this->deleteSource($location, $document->id);
        }

        $from = [
            'entity_id' => $document->entity_id,
            'project_id' => $document->project_id,
            'document_type' => $document->document_type,
        ];

        $document->entity_id = $entity->id;
        $document->project_id = $project->id;
        $document->document_type = $documentType;
        $document->folder_locked = true;
        $document->file_name = basename($targetPath);
        $document->file_path = $targetPath;
        $document->modified_by_user_id = Auth::id();
        $document->save();

        UserActivityLogger::log(UserActivity::ACTION_MOVED, $document, ['moved_from' => $from]);

        return true;
    }

    /**
     * Copy a document (file + record) into another entity / project / folder.
     */
    public function copy(Document $document, Entity $entity, Project $project, string $documentType): ?Document
    {
        $location = DocumentLocationResolver::resolve((string) $document->file_path);
        if ($location === null) {
            return null;
        }

        $disk = config('filesystems.default');
        $targetPath = $this->uniqueTargetPath($disk, $entity, $project, $documentType, (string) $document->file_name);

        if (! $this->copyStoredFile($location, $disk, $targetPath)) {
            return null;
        }

        $copy = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'discipline' => $document->discipline,
            'document_type' => $documentType,
            'folder_locked' => true,
            'file_name' => basename($targetPath),
            'file_path' => $targetPath,
            'ocr_text' => $document->ocr_text,
            'modified_by_user_id' => Auth::id(),
        ]);

        UserActivityLogger::log(UserActivity::ACTION_COPIED, $copy, ['copied_from_id' => $document->id]);

        return $copy;
    }

    /**
     * Build a ZIP of the given documents in a temp file. Returns the path, or null when nothing was added.
     *
     * @param  iterable<int, Document>  $documents
     */
    public function buildZip(iterable $documents): ?string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'dms_zip_');
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $tempFiles = [];
        $usedNames = [];
        $added = 0;

        foreach ($documents as $document) {
            $location = DocumentLocationResolver::resolve((string) $document->file_path);
            if ($location === null) {
                continue;
            }

            if ($location['source'] === 'disk') {
                $stream = Storage::disk($location['disk'])->readStream($location['path']);
                if (! is_resource($stream)) {
                    continue;
                }
                $tempPath = tempnam(sys_get_temp_dir(), 'dms_zip_part_');
                $out = fopen($tempPath, 'wb');
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);
                $tempFiles[] = $tempPath;
                $sourcePath = $tempPath;
            } else {
                $sourcePath = $location['path'];
            }

            $zip->addFile($sourcePath, $this->uniqueZipName((string) $document->file_name, $usedNames));
            $added++;
        }

        $zip->close();

        foreach ($tempFiles as $tempPath) {
            @unlink($tempPath);
        }

        if ($added === 0) {
            @unlink($zipPath);

            return null;
        }

        return $zipPath;
    }

    protected function uniqueTargetPath(string $disk, Entity $entity, Project $project, string $documentType, string $fileName): string
    {
        $folder = 'documents/'
            .Str::slug($entity->name).'/'
            .Str::slug($project->project_number).'/'
            .Str::slug($documentType);

        $path = $folder.'/'.$fileName;
        if (! Storage::disk($disk)->exists($path)) {
            return $path;
        }

        $extension = (string) pathinfo($fileName, PATHINFO_EXTENSION);
        $base = (string) pathinfo($fileName, PATHINFO_FILENAME);
        for ($i = 1; $i <= 200; $i++) {
            $candidate = $folder.'/'.$base.' ('.$i.')'.($extension !== '' ? '.'.$extension : '');
            if (! Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $folder.'/'.$base.' ('.Str::lower((string) Str::ulid()).')'.($extension !== '' ? '.'.$extension : '');
    }

    /**
     * @param  array{source:'disk',disk:string,path:string}|array{source:'file',path:string}  $location
     */
    protected function copyStoredFile(array $location, string $disk, string $targetPath): bool
    {
        $stream = $location['source'] === 'disk'
            ? Storage::disk($location['disk'])->readStream($location['path'])
            : @fopen($location['path'], 'rb');

        if (! is_resource($stream)) {
            return false;
        }

        try {
            $written = Storage::disk($disk)->writeStream($targetPath, $stream);
        } catch (\Throwable $e) {
            Log::warning('Bulk document copy failed', ['target' => $targetPath, 'error' => $e->getMessage()]);
            $written = false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $written !== false && Storage::disk($disk)->exists($targetPath);
    }

    /**
     * @param  array{source:'disk',disk:string,path:string}|array{source:'file',path:string}  $location
     */
    protected function deleteSource(array $location, int $documentId): void
    {
        try {
            if ($location['source'] === 'disk') {
                Storage::disk($location['disk'])->delete($location['path']);
            } else {
                @unlink($location['path']);
            }
        } catch (\Throwable $e) {
            Log::warning('Bulk document move: source cleanup failed', ['document_id' => $documentId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, true>  $usedNames
     */
    protected function uniqueZipName(string $fileName, array &$usedNames): string
    {
        $name = $fileName !== '' ? $fileName : 'document';
        $extension = (string) pathinfo($name, PATHINFO_EXTENSION);
        $base = (string) pathinfo($name, PATHINFO_FILENAME);

        $candidate = $name;
        for ($i = 1; isset($usedNames[strtolower($candidate)]); $i++) {
            $candidate = $base.' ('.$i.')'.($extension !== '' ? '.'.$extension : '');
        }
        $usedNames[strtolower($candidate)] = true;

        return $candidate;
    }
}
