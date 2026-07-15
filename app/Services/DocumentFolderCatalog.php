<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentMainFolder;
use App\Models\DocumentSubfolder;
use App\Models\UserFolderAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DocumentFolderCatalog
{
    public const CACHE_KEY = 'document_sidebar_folder_tree';

    /**
     * @return array<string, list<string>>
     */
    public static function tree(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            $mains = DocumentMainFolder::query()
                ->with(['subfolders' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            if ($mains->isEmpty()) {
                return DocumentFilenameParser::hardcodedSidebarFolderTree();
            }

            $tree = [];
            foreach ($mains as $main) {
                $tree[$main->name] = $main->subfolders->pluck('name')->values()->all();
            }

            return $tree;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function renameMainFolder(DocumentMainFolder $folder, string $newName): void
    {
        $oldName = $folder->name;
        if ($oldName === $newName) {
            return;
        }

        DB::transaction(function () use ($folder, $oldName, $newName) {
            $folder->update(['name' => $newName]);
            UserFolderAccess::query()
                ->where('main_folder', $oldName)
                ->update(['main_folder' => $newName]);
        });

        self::clearCache();
    }

    public static function renameSubfolder(DocumentSubfolder $subfolder, string $newName): void
    {
        $oldName = $subfolder->name;
        if ($oldName === $newName) {
            return;
        }

        DB::transaction(function () use ($subfolder, $oldName, $newName) {
            $subfolder->update(['name' => $newName]);
            Document::query()
                ->where('document_type', $oldName)
                ->update(['document_type' => $newName]);
            UserFolderAccess::query()
                ->where('document_type', $oldName)
                ->update(['document_type' => $newName]);
        });

        self::clearCache();
    }
}
