<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fast inline preview: presigned GET to R2/S3 (browser loads directly), not via Laravel proxy.
 */
class DocumentPreviewUrl
{
    /**
     * URL suitable for iframe / window.open inline viewing.
     */
    public static function inlineUrl(Document $document): ?string
    {
        $path = (string) $document->file_path;
        $location = DocumentLocationResolver::resolve($path);

        if ($location === null) {
            return null;
        }

        if (($location['source'] ?? '') === 'disk' && ($location['disk'] ?? '') === 's3') {
            $presigned = self::presignedGetUrl($location['path'], $document->file_name);
            if ($presigned !== null) {
                return $presigned;
            }
        }

        return route('documents.view', ['id' => $document->id]);
    }

    /**
     * If non-null, viewPdf should redirect here instead of streaming through the app server.
     */
    public static function presignedRedirectUrl(Document $document): ?string
    {
        $path = (string) $document->file_path;
        $location = DocumentLocationResolver::resolve($path);

        if ($location === null || ($location['source'] ?? '') !== 'disk' || ($location['disk'] ?? '') !== 's3') {
            return null;
        }

        return self::presignedGetUrl($location['path'], $document->file_name);
    }

    public static function fileSizeBytes(Document $document): ?int
    {
        $path = (string) $document->file_path;
        $location = DocumentLocationResolver::resolve($path);

        if ($location === null || ($location['source'] ?? '') !== 'disk') {
            return null;
        }

        try {
            if (! Storage::disk($location['disk'])->exists($location['path'])) {
                return null;
            }

            return (int) Storage::disk($location['disk'])->size($location['path']);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function presignedGetUrl(string $objectKey, string $fileName): ?string
    {
        try {
            $mime = self::mimeFromFileName($fileName);
            $safeName = str_replace(['"', "\r", "\n"], '', $fileName);

            return Storage::disk('s3')->temporaryUrl(
                $objectKey,
                now()->addMinutes(30),
                [
                    'ResponseContentType' => $mime,
                    'ResponseContentDisposition' => 'inline; filename="'.$safeName.'"',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Presigned preview URL failed', [
                'path' => $objectKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected static function mimeFromFileName(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
