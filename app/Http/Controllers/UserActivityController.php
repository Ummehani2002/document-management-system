<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivity;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentPreviewUrl;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserActivityController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->query('user_id', 0);
        $action = trim((string) $request->query('action', ''));

        $query = UserActivity::query()
            ->with(['user', 'document.project', 'document.entity', 'document.modifiedBy'])
            ->latest('id');

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($action !== '') {
            $query->where('action', $action);
        }

        $activities = $query->paginate(25)->withQueryString();
        $documentIds = $activities->getCollection()
            ->pluck('document_id')
            ->filter()
            ->unique()
            ->values();

        $createdByUsers = UserActivity::query()
            ->whereIn('document_id', $documentIds)
            ->where('action', UserActivity::ACTION_UPLOADED)
            ->with('user:id,username')
            ->orderBy('id')
            ->get()
            ->unique('document_id')
            ->keyBy('document_id')
            ->map(fn (UserActivity $activity) => $activity->user);

        $activities->getCollection()->transform(function (UserActivity $activity) use ($createdByUsers) {
            $activity->grid_row = $this->buildActivityGridRow($activity, $createdByUsers);

            return $activity;
        });

        return view('user-activities.index', [
            'activities' => $activities,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'actions' => UserActivity::ACTION_LABELS,
            'selectedUserId' => $userId,
            'selectedAction' => $action,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\User|null>  $createdByUsers
     * @return array<string, string>
     */
    protected function buildActivityGridRow(UserActivity $activity, $createdByUsers): array
    {
        $document = $activity->document;
        $props = $activity->properties ?? [];

        if ($document === null) {
            $fileName = (string) ($props['file_name'] ?? '—');

            return [
                'file_type' => $this->fileTypeFromName($fileName),
                'file_name' => $fileName !== '' ? $fileName : '—',
                'date' => format_model_datetime($activity, 'created_at'),
                'reference_no' => '—',
                'subject' => '—',
                'project_number' => '—',
                'project_name' => '—',
                'project_client' => '—',
                'project_consultant' => '—',
                'project_discipline' => '—',
                'modified_date' => '—',
                'modified_by' => '—',
                'created_date' => '—',
                'created_by' => '—',
                'file_size' => '—',
                'item_child_count' => '0',
                'folder_child_count' => '0',
            ];
        }

        $meta = DocumentFilenameParser::extractReferenceAndSubject($document->ocr_text, $document->file_name);
        $fileSizeBytes = DocumentPreviewUrl::fileSizeBytes($document);

        return [
            'file_type' => $this->fileTypeFromName($document->file_name),
            'file_name' => $document->file_name,
            'date' => format_model_datetime($activity, 'created_at'),
            'reference_no' => $meta['reference_no'] ?? '—',
            'subject' => $meta['subject'] ?? '—',
            'project_number' => $document->project?->project_number ?? '—',
            'project_name' => $document->project?->project_name ?? '—',
            'project_client' => $document->project?->client_name ?? '—',
            'project_consultant' => $document->project?->consultant ?? '—',
            'project_discipline' => $document->discipline ?: '—',
            'modified_date' => format_model_datetime($document, 'updated_at'),
            'modified_by' => $document->modifiedBy?->username ?? '—',
            'created_date' => format_model_datetime($document, 'created_at'),
            'created_by' => $createdByUsers->get($document->id)?->username ?? '—',
            'file_size' => $this->formatFileSize($fileSizeBytes),
            'item_child_count' => '0',
            'folder_child_count' => '0',
        ];
    }

    protected function fileTypeFromName(string $fileName): string
    {
        $extension = strtoupper((string) pathinfo($fileName, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : '—';
    }

    protected function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
