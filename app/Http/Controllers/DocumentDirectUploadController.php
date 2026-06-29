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
use App\Services\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Large files: browser → R2 (presigned PUT) can hit CORS. Chunked path uses small POSTs to Laravel,
 * then S3 multipart upload (UploadPart + CompleteMultipartUpload) so the app never merges the full file on disk.
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

    /**
     * @return array<string, mixed>|JsonResponse
     */
    protected function buildLargeUploadSession(Request $request): array|JsonResponse
    {
        if ($this->s3Client() === null) {
            return response()->json([
                'message' => 'Large uploads require FILESYSTEM_DISK=s3 (e.g. Cloudflare R2).',
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
        $objectBaseName = Str::lower((string) Str::ulid()).'.'.$originalExt;

        if ($orphanCandidate !== null) {
            $orphanCategory = (string) ($orphanCandidate->document_type ?: $category);
            $reattachFolder = 'documents/'
                .Str::slug($entity->name).'/'
                .Str::slug($project->project_number).'/'
                .Str::slug($orphanCategory);
            $reattachName = (string) $orphanCandidate->file_name;
            $storedFileNameForDb = $reattachName;
            $orphanDocumentId = (int) $orphanCandidate->id;
            $storageKey = $reattachFolder.'/'.$objectBaseName;
        } else {
            $storedFileNameForDb = DocumentFileVersioning::buildVersionedFilename($filename, $project->id, $category);
            $storageKey = $folderPath.'/'.$objectBaseName;
        }

        return [
            'disk' => $disk,
            'key' => $storageKey,
            'filename' => $filename,
            'original_ext' => $originalExt,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'discipline_name' => $disciplineName,
            'category' => $category,
            'stored_file_name' => $storedFileNameForDb,
            'orphan_document_id' => $orphanDocumentId,
        ];
    }

    /**
     * Start chunked upload using S3 multipart (R2-compatible): parts are assembled in object storage, not on this server.
     */
    public function chunkInit(Request $request): JsonResponse
    {
        $built = $this->buildLargeUploadSession($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        $minPart = 5 * 1024 * 1024; // S3/R2: every part except the last must be ≥ 5 MiB
        $chunkSize = max($minPart, (int) env('DOC_UPLOAD_CHUNK_BYTES', $minPart));
        $fileSize = (int) $built['file_size'];
        $totalChunks = (int) max(1, (int) ceil($fileSize / $chunkSize));
        if ($totalChunks > 9000) {
            return response()->json(['message' => 'File is too large for the current chunk size. Increase DOC_UPLOAD_CHUNK_BYTES in .env.'], 422);
        }

        $client = $this->s3Client();
        if ($client === null) {
            return response()->json(['message' => 'S3 client not available.'], 422);
        }

        $bucket = (string) config('filesystems.disks.s3.bucket');
        $key = (string) $built['key'];
        $contentType = (string) $built['content_type'];

        try {
            $created = $client->createMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $key,
                'ContentType' => $contentType,
            ]);
            $uploadId = (string) $created['UploadId'];
        } catch (\Throwable $e) {
            Log::warning('createMultipartUpload failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not start multipart upload: '.$e->getMessage()], 500);
        }

        $payload = [
            'v' => 3,
            'exp' => time() + 3600,
            'upload_id' => $uploadId,
            'bucket' => $bucket,
            'key' => $key,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'disk' => $built['disk'],
            'original_name' => $built['filename'],
            'stored_file_name' => $built['stored_file_name'],
            'entity_id' => $built['entity_id'],
            'project_id' => $built['project_id'],
            'discipline_name' => $built['discipline_name'],
            'category' => $built['category'],
            'file_size' => $built['file_size'],
            'content_type' => $built['content_type'],
            'orphan_document_id' => $built['orphan_document_id'],
        ];

        try {
            $token = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::warning('Chunk upload init encrypt failed', ['error' => $e->getMessage()]);
            try {
                $client->abortMultipartUpload([
                    'Bucket' => $bucket,
                    'Key' => $key,
                    'UploadId' => $uploadId,
                ]);
            } catch (\Throwable) {
            }

            return response()->json(['message' => 'Could not start upload session.'], 500);
        }

        return response()->json([
            'token' => $token,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
        ]);
    }

    public function chunkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|min:10',
            'index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:102400',
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($validated['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid or expired upload session.'], 410);
        }

        if (! is_array($payload) || (int) ($payload['v'] ?? 0) !== 3) {
            return response()->json(['message' => 'Invalid upload session. Refresh the page and try again.'], 422);
        }

        if (($payload['exp'] ?? 0) < time()) {
            return response()->json(['message' => 'Upload session expired.'], 410);
        }

        $disk = (string) ($payload['disk'] ?? '');
        if ($disk !== 's3') {
            return response()->json(['message' => 'Invalid disk.'], 422);
        }

        $uploadId = (string) ($payload['upload_id'] ?? '');
        $bucket = (string) ($payload['bucket'] ?? '');
        $objectKey = (string) ($payload['key'] ?? '');
        $chunkSize = (int) ($payload['chunk_size'] ?? 0);
        $totalChunks = (int) ($payload['total_chunks'] ?? 0);
        $fileSize = (int) ($payload['file_size'] ?? 0);
        $index = (int) $validated['index'];

        if ($uploadId === '' || $bucket === '' || $objectKey === '' || ! str_starts_with($objectKey, 'documents/')
            || $chunkSize < 1 || $totalChunks < 1 || $index >= $totalChunks) {
            return response()->json(['message' => 'Invalid chunk index.'], 422);
        }

        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');
        $bytes = (int) $chunk->getSize();
        $expected = $index < $totalChunks - 1 ? $chunkSize : ($fileSize - ($chunkSize * ($totalChunks - 1)));
        if ($expected < 0) {
            $expected = 0;
        }
        if ($bytes !== $expected) {
            return response()->json([
                'message' => 'Chunk size mismatch (expected '.$expected.' bytes, got '.$bytes.'). Re-upload from chunk 0.',
            ], 422);
        }

        $client = $this->s3Client();
        if ($client === null) {
            return response()->json(['message' => 'S3 client not available.'], 422);
        }

        $partNumber = $index + 1;

        $stream = fopen($chunk->getRealPath(), 'rb');
        if ($stream === false) {
            return response()->json(['message' => 'Could not read chunk.'], 500);
        }
        try {
            $client->uploadPart([
                'Bucket' => $bucket,
                'Key' => $objectKey,
                'UploadId' => $uploadId,
                'PartNumber' => $partNumber,
                'Body' => $stream,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Multipart uploadPart failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not upload part to storage: '.$e->getMessage()], 500);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return response()->json(['ok' => true, 'index' => $index]);
    }

    public function chunkFinish(Request $request): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');

        $validated = $request->validate([
            'token' => 'required|string|min:10',
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($validated['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid or expired upload session.'], 410);
        }

        if (! is_array($payload) || (int) ($payload['v'] ?? 0) !== 3) {
            return response()->json(['message' => 'Invalid upload session.'], 422);
        }

        if (($payload['exp'] ?? 0) < time()) {
            return response()->json(['message' => 'Upload session expired.'], 410);
        }

        $disk = (string) ($payload['disk'] ?? '');
        $uploadId = (string) ($payload['upload_id'] ?? '');
        $bucket = (string) ($payload['bucket'] ?? '');
        $finalKey = (string) ($payload['key'] ?? '');
        $totalChunks = (int) ($payload['total_chunks'] ?? 0);
        $expectedSize = (int) ($payload['file_size'] ?? 0);

        if ($disk !== 's3' || $uploadId === '' || $bucket === '' || $finalKey === '' || ! str_starts_with($finalKey, 'documents/') || $totalChunks < 1) {
            return response()->json(['message' => 'Invalid session.'], 422);
        }

        $client = $this->s3Client();
        if ($client === null) {
            return response()->json(['message' => 'S3 client not available.'], 422);
        }

        $listed = [];
        $marker = null;
        try {
            do {
                $args = [
                    'Bucket' => $bucket,
                    'Key' => $finalKey,
                    'UploadId' => $uploadId,
                ];
                if ($marker !== null) {
                    $args['PartNumberMarker'] = $marker;
                }
                $page = $client->listParts($args);
                foreach ($page['Parts'] ?? [] as $part) {
                    $listed[] = [
                        'PartNumber' => (int) $part['PartNumber'],
                        'ETag' => (string) $part['ETag'],
                    ];
                }
                $marker = ! empty($page['IsTruncated']) ? ($page['NextPartNumberMarker'] ?? null) : null;
            } while ($marker !== null);
        } catch (\Throwable $e) {
            Log::warning('listParts failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not verify uploaded parts: '.$e->getMessage()], 500);
        }

        usort($listed, fn (array $a, array $b): int => $a['PartNumber'] <=> $b['PartNumber']);

        if (count($listed) !== $totalChunks) {
            try {
                $client->abortMultipartUpload([
                    'Bucket' => $bucket,
                    'Key' => $finalKey,
                    'UploadId' => $uploadId,
                ]);
            } catch (\Throwable) {
            }

            return response()->json([
                'message' => 'Expected '.$totalChunks.' parts, found '.count($listed).'. Re-upload the file.',
            ], 422);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            if (($listed[$i]['PartNumber'] ?? 0) !== $i + 1) {
                try {
                    $client->abortMultipartUpload([
                        'Bucket' => $bucket,
                        'Key' => $finalKey,
                        'UploadId' => $uploadId,
                    ]);
                } catch (\Throwable) {
                }

                return response()->json(['message' => 'Part sequence error. Re-upload the file.'], 422);
            }
        }

        try {
            $client->completeMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $finalKey,
                'UploadId' => $uploadId,
                'MultipartUpload' => [
                    'Parts' => $listed,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('completeMultipartUpload failed', ['error' => $e->getMessage()]);
            try {
                $client->abortMultipartUpload([
                    'Bucket' => $bucket,
                    'Key' => $finalKey,
                    'UploadId' => $uploadId,
                ]);
            } catch (\Throwable) {
            }

            return response()->json(['message' => 'Could not finalize multipart upload: '.$e->getMessage()], 500);
        }

        $actualSize = (int) Storage::disk($disk)->size($finalKey);
        if ($expectedSize > 0 && $actualSize !== $expectedSize) {
            Storage::disk($disk)->delete($finalKey);

            return response()->json(['message' => 'Object size mismatch after upload (expected '.$expectedSize.', got '.$actualSize.').'], 422);
        }

        $finalize = [
            'v' => 1,
            'exp' => time() + 120,
            'disk' => $disk,
            'key' => $finalKey,
            'original_name' => (string) ($payload['original_name'] ?? ''),
            'stored_file_name' => (string) ($payload['stored_file_name'] ?? ''),
            'entity_id' => (int) ($payload['entity_id'] ?? 0),
            'project_id' => (int) ($payload['project_id'] ?? 0),
            'discipline_name' => $payload['discipline_name'] ?? null,
            'category' => (string) ($payload['category'] ?? 'Other'),
            'file_size' => $expectedSize,
            'content_type' => (string) ($payload['content_type'] ?? ''),
            'orphan_document_id' => isset($payload['orphan_document_id']) ? (int) $payload['orphan_document_id'] : null,
        ];

        return $this->finalizeUploadedObject($finalize, $finalKey);
    }

    public function presign(Request $request): JsonResponse
    {
        $built = $this->buildLargeUploadSession($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        $client = $this->s3Client();
        if ($client === null) {
            return response()->json(['message' => 'S3 client not available.'], 422);
        }

        $bucket = (string) config('filesystems.disks.s3.bucket');
        $storageKey = $built['key'];
        $contentType = $built['content_type'];

        try {
            $cmd = $client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key' => $storageKey,
                'ContentType' => $contentType,
            ]);
            $presigned = (string) $client->createPresignedRequest($cmd, '+45 minutes')->getUri();
        } catch (\Throwable $e) {
            Log::warning('Direct upload presign failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Could not create upload URL: '.$e->getMessage(),
            ], 500);
        }

        $expiresAt = time() + 45 * 60;
        $payload = [
            'v' => 1,
            'exp' => $expiresAt,
            'disk' => $built['disk'],
            'key' => $storageKey,
            'original_name' => $built['filename'],
            'stored_file_name' => $built['stored_file_name'],
            'entity_id' => $built['entity_id'],
            'project_id' => $built['project_id'],
            'discipline_name' => $built['discipline_name'],
            'category' => $built['category'],
            'file_size' => $built['file_size'],
            'content_type' => $contentType,
            'orphan_document_id' => $built['orphan_document_id'],
        ];

        try {
            $token = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::warning('Direct upload token encrypt failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not start upload session.'], 500);
        }

        return response()->json([
            'upload_url' => $presigned,
            'token' => $token,
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => $contentType,
            ],
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|min:10',
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($validated['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid or expired upload session. Start the upload again.'], 410);
        }

        if (! is_array($payload) || (int) ($payload['v'] ?? 0) !== 1) {
            return response()->json(['message' => 'Invalid upload session.'], 422);
        }

        if (($payload['exp'] ?? 0) < time()) {
            return response()->json(['message' => 'Upload session expired. Start again.'], 410);
        }

        $disk = (string) ($payload['disk'] ?? '');
        $key = (string) ($payload['key'] ?? '');
        if ($disk !== 's3' || $key === '' || ! str_starts_with($key, 'documents/')) {
            return response()->json(['message' => 'Invalid upload session.'], 422);
        }

        if (! Storage::disk($disk)->exists($key)) {
            return response()->json([
                'message' => 'Object not found in bucket. If you used browser PUT, configure R2 CORS; otherwise retry the upload.',
            ], 422);
        }

        $actualSize = (int) Storage::disk($disk)->size($key);
        $expectedSize = (int) ($payload['file_size'] ?? 0);
        if ($expectedSize > 0 && $actualSize !== $expectedSize) {
            Storage::disk($disk)->delete($key);

            return response()->json(['message' => 'Uploaded size did not match declared size; partial file removed.'], 422);
        }

        return $this->finalizeUploadedObject($payload, $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function finalizeUploadedObject(array $payload, string $key): JsonResponse
    {
        $disk = (string) ($payload['disk'] ?? 's3');

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
                UserActivityLogger::reattached($document, [
                    'upload_mode' => (string) ($payload['upload_mode'] ?? 'auto'),
                ]);
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
        UserActivityLogger::uploaded($document, [
            'upload_mode' => (string) ($payload['upload_mode'] ?? 'auto'),
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
