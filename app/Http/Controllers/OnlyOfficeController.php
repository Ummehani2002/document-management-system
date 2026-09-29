<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentAccessService;
use App\Services\DocumentLocationResolver;
use App\Services\DocumentVersionSaver;
use App\Services\OnlyOfficeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OnlyOfficeController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access,
        protected OnlyOfficeService $onlyOffice
    ) {}

    /**
     * Signed download URL for OnlyOffice Document Server (no session cookie).
     */
    public function source(Request $request, int $id)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $document = Document::find($id);
        if ($document === null) {
            abort(404);
        }

        $path = (string) $document->file_path;
        $location = DocumentLocationResolver::resolve($path);
        if ($location === null) {
            abort(404);
        }

        $mimeType = match (strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };

        if ($location['source'] === 'disk') {
            return Storage::disk($location['disk'])->response(
                $location['path'],
                $document->file_name,
                ['Content-Type' => $mimeType]
            );
        }

        return response()->file($location['path'], ['Content-Type' => $mimeType]);
    }

    /**
     * Ask OnlyOffice to send the current edited file to the callback (status 6).
     */
    public function forceSave(Request $request, int $id)
    {
        $document = Document::find($id);
        if ($document === null) {
            abort(404);
        }

        if (! $this->access->canAccessDocument($request->user(), $document)) {
            abort(403);
        }

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:128'],
        ]);

        Cache::forget('doc_version_saved_from_'.$document->id);

        $result = $this->onlyOffice->forceSave((string) $validated['key']);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'no_changes' => ((int) ($result['error'] ?? 0)) === 4,
        ]);
    }

    /**
     * OnlyOffice save callback — overwrites the same DMS file (Excel/Word edits persist).
     */
    public function callback(Request $request, int $id)
    {
        $document = Document::find($id);
        if ($document === null) {
            return response()->json(['error' => 1]);
        }

        $payload = $request->all();
        $status = (int) ($payload['status'] ?? 0);

        // 2 = ready for saving after all users closed; 6 = force save while still editing
        if (! in_array($status, [2, 6], true)) {
            return response()->json(['error' => 0]);
        }

        $downloadUrl = (string) ($payload['url'] ?? '');
        if ($downloadUrl === '') {
            return response()->json(['error' => 1]);
        }

        try {
            $response = Http::timeout(120)->get($downloadUrl);
            if (! $response->successful()) {
                throw new \RuntimeException('OnlyOffice download failed: HTTP '.$response->status());
            }

            $modifiedBy = $this->editorUserIdFromPayload($payload);
            $saved = (new DocumentVersionSaver)->overwriteFromContents(
                $document,
                $response->body(),
                $modifiedBy
            );

            Cache::put(
                'doc_version_saved_from_'.$document->id,
                [
                    'new_document_id' => $saved->id,
                    'new_file_name' => $saved->file_name,
                    'overwritten' => true,
                    'saved_at' => now()->toIso8601String(),
                ],
                now()->addMinutes(15)
            );

            Log::info('OnlyOffice document overwritten in DMS', [
                'document_id' => $document->id,
                'status' => $status,
                'file_name' => $saved->file_name,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OnlyOffice callback save failed', [
                'document_id' => $id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 1]);
        }

        return response()->json(['error' => 0]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function editorUserIdFromPayload(array $payload): ?int
    {
        $actions = $payload['actions'] ?? null;
        if (is_array($actions) && isset($actions[0]['userid'])) {
            $id = (int) $actions[0]['userid'];

            return $id > 0 ? $id : null;
        }

        $users = $payload['users'] ?? null;
        if (is_array($users) && isset($users[0])) {
            $id = (int) $users[0];

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
