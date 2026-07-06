<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentLocationResolver;
use App\Services\DocumentVersionSaver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OnlyOfficeController extends Controller
{
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
     * OnlyOffice save callback — creates V1, V2, … instead of overwriting.
     */
    public function callback(Request $request, int $id)
    {
        $document = Document::find($id);
        if ($document === null) {
            return response()->json(['error' => 1]);
        }

        $payload = $request->all();
        $status = (int) ($payload['status'] ?? 0);

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

            $newDocument = (new DocumentVersionSaver)->saveFromContents($document, $response->body());

            \Illuminate\Support\Facades\Cache::put(
                'doc_version_saved_from_'.$document->id,
                [
                    'new_document_id' => $newDocument->id,
                    'new_file_name' => $newDocument->file_name,
                ],
                now()->addMinutes(15)
            );

            Log::info('OnlyOffice version saved', [
                'source_id' => $document->id,
                'new_id' => $newDocument->id,
                'new_file' => $newDocument->file_name,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OnlyOffice callback save failed', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 1]);
        }

        return response()->json(['error' => 0]);
    }
}
