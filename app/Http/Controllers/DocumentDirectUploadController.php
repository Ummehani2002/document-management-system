<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOCR;
use App\Models\Discipline;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentFileVersioning;
use App\Services\DocumentLocationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Browser → presigned PUT → S3/R2 (large files), then small JSON "complete" to Laravel.
 * Avoids HTTP body limits on Laravel Cloud / Cloudflare edge (~100MB).
 */
class DocumentDirectUploadController extends Controller
{
    protected function s3Client(): ?\Aws\S3\S3Client
    {
        if (config('filesystems.default') !== 's3') {
            return null;
        }
        $c = config('filesystems.disks.s3');
        if (empty($c['key']) || empty($c['secret']) || empty($c['bucket'])) {
            return null;
        }

        return new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => $c['region'] ?? 'auto',
            'endpoint' => ! empty($c['endpoint']) ? $c['endpoint'] : null,
            'use_path_style_endpoint' => (bool) ($c['use_path_style_endpoint'] ?? false),
            'credentials' => [
                'key' => $c['key'],
                'secret' => $c['secret'],
            ],
        ]);
    }

    public function presign(Request $request)
    {
        $client = $this->s3Client();
        if ($client === null) {
            return response()->json([
                'message' => 'Direct upload is only available when FILESYSTEM_DISK=s3 (e.g. Cloudflare R2). Use normal upload for local disk.',
            ], 422);
        }

        $maxFileMb = max(1, (int) env('DOC_UPLOAD_MAX_FILE_MB', 1024));
        $maxFileBytes = $maxFileMb * 1024 * 1024;

        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        $validMainFolders = array_keys($folderTree);
        $validSubfolders = array_values(array_unique(array_merge(
            ['Other'],
            ...array_values($folderTree)
        )));

        $validated = $request->validate([
            'upload_mode' => ['nullable', 'string', Rule::in(['auto', 'manual'])],
            'entity_id' => 'required|exists:entities,id',
            'project_id' => 'required|exists:projects,id',
            'discipline_id' => 'nullable|integer|exists:disciplines,id',
            'main_folder' => ['nullable', 'string', Rule::in($validMainFolders)],
            'document_type' => ['nullable', 'string', Rule::in($validSubfolders)],
            'filename' => 'required|string|max:512',
            'file_size' => 'required|integer|min:1|max:'.$maxFileBytes,
            'content_type' => 'required|string|max:255',
        ]);

        $filename = basename(str_replace(["\0", '\\'], '', (string) $validated['filename']));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return response()->json(['message' => 'Invalid filename.'], 422);
        }

        $originalExt = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        if (! in_array($originalExt, $allowed, true)) {
            return response()->json(['message' => 'The file must be one of: pdf, doc, docx, xls, xlsx.'], 422);
        }

        $contentType = $this->normalizeContentType((string) $validated['content_type'], $originalExt);
        $fileSize = (int) $validated['file_size'];

        $uploadMode = (string) ($validated['upload_mode'] ?? 'auto');
        $manualMainFolder = trim((string) ($validated['main_folder'] ?? ''));
        $manualSubfolder = trim((string) ($validated['document_type'] ?? ''));
        if ($uploadMode === 'manual') {
            if ($manualMainFolder === '' || $manualSubfolder === '') {
                return response()->json(['message' => 'Select category and folder for manual upload.'], 422);
            }
            $allowedSubfolders = $folderTree[$manualMainFolder] ?? [];
            if (! in_array($manualSubfolder, $allowedSubfolders, true)) {
                return response()->json(['message' => 'Selected folder does not belong to selected category.'], 422);
            }
        }

        $entity = Entity::findOrFail($validated['entity_id']);
        $project = Project::where('id', $validated['project_id'])->where('entity_id', $entity->id)->firstOrFail();

        $disciplineName = null;
        if (! empty($validated['discipline_id'])) {
            $disciplineName = Discipline::whereKey((int) $validated['discipline_id'])->value('name');
        }

        $category = 'Other';
        if ($uploadMode === 'manual') {
            $category = $manualSubfolder;
        } else {
            $result = DocumentFilenameParser::classifyForAutomation($filename, null);
            $predicted = trim((string) ($result['document_category'] ?? 'Other'));
            if ($predicted !== '' && in_array($predicted, $validSubfolders, true)) {
                $category = $predicted;
            }
        }

        $folderPath = 'documents/'
            .Str::slug($entity->name).'/'
            .Str::slug($project->project_number).'/'
            .Str::slug($category);

        $candidates = Document::query()
            ->where('project_id', $project->id)
            ->get(['id', 'file_name', 'file_path', 'document_type', 'entity_id']);

        $uploadedBase = pathinfo($filename, PATHINFO_FILENAME);
        $targetKey = DocumentFileVersioning::versionKey($uploadedBase);
        $uploadedVersion = DocumentFileVersioning::extractVersionNumber($uploadedBase);

        $orphanCandidate = null;
        foreach ($candidates as $candidate) {
            $existingBase = pathinfo((string) $candidate->file_name, PATHINFO_FILENAME);
            if (DocumentFileVersioning::versionKey($existingBase) !== $targetKey) {
                continue;
            }
            if (DocumentFileVersioning::extractVersionNumber($existingBase) !== $uploadedVersion) {
                continue;
            }
            if (DocumentLocationResolver::resolve((string) $candidate->file_path) !== null) {
                continue;
            }
            $orphanCandidate = $candidate;
            break;
        }

        $disk = config('filesystems.default');
        $orphanDocumentId = null;
        if ($orphanCandidate !== null) {
            $orphanCategory = (string) ($orphanCandidate->document_type ?: $category);
            $reattachFolder = 'documents/'
                .Str::slug($entity->name).'/'
                .Str::slug($project->project_number).'/'
                .Str::slug($orphanCategory);
            $reattachName = (string) $orphanCandidate->file_name;
            $storageKey = $reattachFolder.'/'.$reattachName;
            $storedFileNameForDb = $reattachName;
            $orphanDocumentId = (int) $orphanCandidate->id;
        } else {
            $storedFileNameForDb = DocumentFileVersioning::buildVersionedFilename($filename, $project->id, $category);
            $storageKey = $folderPath.'/'.$storedFileNameForDb;
        }

        $bucket = (string) config('filesystems.disks.s3.bucket');
        $cmd = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $storageKey,
            'ContentType' => $contentType,
        ]);
        $presigned = (string) $client->createPresignedRequest($cmd, '+45 minutes')->getUri();

        $token = Str::random(64);
        Cache::put('doc_direct_upload:'.$token, [
            'disk' => $disk,
            'key' => $storageKey,
            'original_name' => $filename,
            'stored_file_name' => $storedFileNameForDb,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'discipline_name' => $disciplineName,
            'category' => $category,
            'file_size' => $fileSize,
            'content_type' => $contentType,
            'orphan_document_id' => $orphanDocumentId,
        ], now()->addMinutes(50));

        return response()->json([
            'upload_url' => $presigned,
            'token' => $token,
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => $contentType,
            ],
        ]);
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $cacheKey = 'doc_direct_upload:'.trim((string) $validated['token']);
        $payload = Cache::pull($cacheKey);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Upload session expired or invalid. Start again.'], 410);
        }

        $disk = (string) ($payload['disk'] ?? '');
        $key = (string) ($payload['key'] ?? '');
        if ($disk !== 's3' || $key === '' || ! str_starts_with($key, 'documents/')) {
            return response()->json(['message' => 'Invalid upload session.'], 422);
        }

        if (! Storage::disk($disk)->exists($key)) {
            return response()->json([
                'message' => 'Object not found in bucket. Check R2 CORS (PUT from your site origin) and that the browser PUT succeeded.',
            ], 422);
        }

        $actualSize = (int) Storage::disk($disk)->size($key);
        $expectedSize = (int) ($payload['file_size'] ?? 0);
        if ($expectedSize > 0 && $actualSize !== $expectedSize) {
            Storage::disk($disk)->delete($key);

            return response()->json(['message' => 'Uploaded size did not match declared size; partial file removed.'], 422);
        }

        $entity = Entity::find((int) ($payload['entity_id'] ?? 0));
        $project = Project::find((int) ($payload['project_id'] ?? 0));
        if (! $entity || ! $project || $project->entity_id !== $entity->id) {
            Storage::disk($disk)->delete($key);

            return response()->json(['message' => 'Invalid project context.'], 422);
        }

        $originalName = (string) ($payload['original_name'] ?? '');
        $storedFileName = (string) ($payload['stored_file_name'] ?? '');
        $category = (string) ($payload['category'] ?? 'Other');
        $disciplineName = $payload['discipline_name'] ?? null;
        $disciplineName = is_string($disciplineName) && $disciplineName !== '' ? $disciplineName : null;
        $orphanDocumentId = isset($payload['orphan_document_id']) ? (int) $payload['orphan_document_id'] : null;

        $enableDeepDuplicateCheck = (bool) env('DOC_DEEP_DUP_CHECK', false);

        if ($orphanDocumentId !== null && $orphanDocumentId > 0) {
            $document = Document::find($orphanDocumentId);
            if ($document) {
                $document->entity_id = $entity->id;
                $document->project_id = $project->id;
                if ($disciplineName !== null) {
                    $document->discipline = $disciplineName;
                }
                $document->file_path = $key;
                $document->ocr_text = null;
                $document->save();
                try {
                    $this->dispatchProcessOcr($document->id);
                } catch (\Throwable $e) {
                    Log::warning('ProcessOCR on direct re-attach failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
                }

                return response()->json(['message' => 'File attached successfully.', 'document_id' => $document->id]);
            }
        }

        $candidates = Document::query()
            ->where('project_id', $project->id)
            ->get(['id', 'file_name', 'file_path', 'document_type', 'entity_id']);

        $uploadedHash = $enableDeepDuplicateCheck ? $this->hashDiskObject($disk, $key) : null;
        if ($uploadedHash !== null) {
            foreach ($candidates as $candidate) {
                $existingBase = pathinfo((string) $candidate->file_name, PATHINFO_FILENAME);
                if (DocumentFileVersioning::versionKey($existingBase) !== DocumentFileVersioning::versionKey(pathinfo($originalName, PATHINFO_FILENAME))) {
                    continue;
                }
                $existingHash = $this->hashStoredDocumentPath((string) $candidate->file_path);
                if ($existingHash !== null && hash_equals($existingHash, $uploadedHash)) {
                    Storage::disk($disk)->delete($key);

                    return response()->json(['message' => 'Duplicate file skipped (same content already in this project).', 'duplicate' => true]);
                }
            }
        }

        $document = Document::create([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'discipline' => $disciplineName,
            'document_type' => $category,
            'file_name' => $storedFileName,
            'file_path' => $key,
        ]);
        try {
            $this->dispatchProcessOcr($document->id);
        } catch (\Throwable $e) {
            Log::warning('ProcessOCR on direct upload failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'File uploaded successfully.', 'document_id' => $document->id]);
    }

    protected function normalizeContentType(string $contentType, string $extension): string
    {
        $contentType = trim(explode(';', $contentType)[0]);
        $byExt = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        $expected = $byExt[$extension] ?? 'application/octet-stream';
        $allowed = array_values($byExt);
        if (in_array($contentType, $allowed, true)) {
            return $contentType;
        }

        return $expected;
    }

    protected function hashDiskObject(string $disk, string $path): ?string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false || ! is_resource($stream)) {
            return null;
        }
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    protected function hashStoredDocumentPath(string $filePath): ?string
    {
        $location = DocumentLocationResolver::resolve($filePath);
        if ($location === null) {
            return null;
        }
        if ($location['source'] === 'disk') {
            return $this->hashDiskObject($location['disk'], $location['path']);
        }
        $stream = @fopen($location['path'], 'rb');
        if ($stream === false || ! is_resource($stream)) {
            return null;
        }
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    protected function dispatchProcessOcr(int $documentId): void
    {
        $sync = config('queue.default') === 'sync';
        $envSync = filter_var(env('DMS_OCR_SYNC_ON_UPLOAD', false), FILTER_VALIDATE_BOOLEAN);
        if ($sync || $envSync) {
            (new ProcessOCR($documentId))->handle();

            return;
        }
        ProcessOCR::dispatch($documentId)->afterResponse();
    }
}
