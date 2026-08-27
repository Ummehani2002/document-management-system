<?php

namespace App\Services;

use App\Models\Document;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentVersionPromoter
{
    /**
     * Rename an older version so it becomes the latest in its family (search, download, edit).
     */
    public function promoteAsLatest(Document $document): Document
    {
        $family = DocumentFileVersioning::versionFamilyDocuments($document);

        if (DocumentFileVersioning::isLatestInFamily($document, $family)) {
            return $document;
        }

        $newFileName = DocumentFileVersioning::buildPromotedLatestFilename(
            (string) $document->file_name,
            $family
        );

        if ($newFileName === $document->file_name) {
            throw new \RuntimeException('Could not determine a new version name for this file.');
        }

        $previousFileName = (string) $document->file_name;
        $previousFilePath = (string) $document->file_path;
        $newFilePath = $this->renameStoredFile($document, $newFileName);

        $document->update([
            'file_name' => $newFileName,
            'file_path' => $newFilePath,
            'modified_by_user_id' => Auth::id(),
        ]);

        UserActivityLogger::log(UserActivity::ACTION_REPLACED, $document->fresh(), [
            'promoted_as_latest' => true,
            'previous_file_name' => $previousFileName,
            'previous_file_path' => $previousFilePath,
            'promoted_to_file_name' => $newFileName,
        ]);

        return $document->fresh();
    }

    protected function renameStoredFile(Document $document, string $newFileName): string
    {
        $oldPath = str_replace('\\', '/', (string) $document->file_path);
        $directory = dirname($oldPath);
        $newPath = ($directory === '.' ? '' : $directory.'/').$newFileName;

        if ($newPath === $oldPath) {
            return $oldPath;
        }

        $location = DocumentLocationResolver::resolve($oldPath);

        if ($location !== null && $location['source'] === 'disk') {
            $disk = Storage::disk($location['disk']);

            if ($disk->exists($location['path'])) {
                if ($disk->exists($newPath)) {
                    throw new \RuntimeException('Target file path already exists in storage.');
                }

                $disk->move($location['path'], $newPath);
            }
        }

        return $newPath;
    }
}
