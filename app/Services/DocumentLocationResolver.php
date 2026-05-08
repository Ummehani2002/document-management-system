<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentLocationResolver
{
    /**
     * Resolve a document path across the configured disk and legacy local paths.
     *
     * @return array{source:'disk',disk:string,path:string}|array{source:'file',path:string}|null
     */
    public static function resolve(string $path): ?array
    {
        $rawPath = trim($path);
        if ($rawPath === '') {
            return null;
        }

        $rawPath = str_replace('\\', '/', $rawPath);

        if (is_file($rawPath)) {
            return ['source' => 'file', 'path' => $rawPath];
        }

        $normalizedPath = ltrim($rawPath, '/');
        if ($normalizedPath === '') {
            return null;
        }

        $relativeCandidates = [];
        $relativeCandidates[] = $normalizedPath;
        foreach (['storage/', 'app/', 'app/private/', 'app/public/', 'public/', 'private/'] as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                $relativeCandidates[] = substr($normalizedPath, strlen($prefix));
            }
        }
        $relativeCandidates = array_values(array_unique(array_filter($relativeCandidates)));

        $candidateDisks = array_values(array_unique(array_filter([
            config('filesystems.default'),
            'local',
            'public',
        ])));

        foreach ($candidateDisks as $disk) {
            foreach ($relativeCandidates as $candidatePath) {
                try {
                    if (Storage::disk($disk)->exists($candidatePath)) {
                        return ['source' => 'disk', 'disk' => $disk, 'path' => $candidatePath];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Document lookup disk probe failed', [
                        'disk' => $disk,
                        'path' => $candidatePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $absoluteCandidates = [];
        foreach ($relativeCandidates as $candidatePath) {
            $absoluteCandidates[] = storage_path('app/' . $candidatePath);
            $absoluteCandidates[] = storage_path('app/private/' . $candidatePath);
            $absoluteCandidates[] = storage_path('app/public/' . $candidatePath);
            $absoluteCandidates[] = public_path($candidatePath);
            $absoluteCandidates[] = base_path($candidatePath);
        }
        $absoluteCandidates = array_values(array_unique($absoluteCandidates));

        foreach ($absoluteCandidates as $absolutePath) {
            if (is_file($absolutePath)) {
                return ['source' => 'file', 'path' => $absolutePath];
            }
        }

        return null;
    }
}
