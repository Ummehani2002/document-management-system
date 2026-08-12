<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\DocumentFilenameParser;
use App\Services\DocumentPreviewUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $collection = $activities->getCollection();

        $documentIds = $collection
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

        $projectsById = $this->projectsForActivities($collection);

        $collection->transform(function (UserActivity $activity) use ($createdByUsers, $projectsById) {
            $activity->grid_row = $this->buildActivityGridRow($activity, $createdByUsers, $projectsById);

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
     * @param  Collection<int, UserActivity>  $activities
     * @return Collection<int, Project>
     */
    protected function projectsForActivities(Collection $activities): Collection
    {
        $projectIds = $activities
            ->map(function (UserActivity $activity) {
                if ($activity->document?->project_id) {
                    return (int) $activity->document->project_id;
                }

                return (int) (($activity->properties['project_id'] ?? 0));
            })
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($projectIds->isEmpty()) {
            return collect();
        }

        return Project::query()
            ->whereIn('id', $projectIds)
            ->get(['id', 'project_number', 'project_name', 'client_name', 'consultant'])
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, \App\Models\User|null>  $createdByUsers
     * @param  Collection<int, Project>  $projectsById
     * @return array<string, string>
     */
    protected function buildActivityGridRow(UserActivity $activity, $createdByUsers, Collection $projectsById): array
    {
        $document = $activity->document;
        $props = is_array($activity->properties) ? $activity->properties : [];

        if ($document === null) {
            return $this->buildMissingDocumentRow($activity, $props, $projectsById);
        }

        $meta = DocumentFilenameParser::extractReferenceAndSubject($document->ocr_text, $document->file_name);
        $fileSizeBytes = DocumentPreviewUrl::fileSizeBytes($document);
        $project = $document->project
            ?? $projectsById->get((int) $document->project_id);

        return [
            'file_type' => $this->fileTypeFromName((string) $document->file_name),
            'file_name' => (string) ($document->file_name ?: '—'),
            'date' => format_model_datetime($activity, 'created_at'),
            'reference_no' => $meta['reference_no'] ?? '—',
            'subject' => $meta['subject'] ?? '—',
            'project_number' => $project?->project_number ?? '—',
            'project_name' => $project?->project_name ?? '—',
            'project_client' => $project?->client_name ?? '—',
            'project_consultant' => $project?->consultant ?? '—',
            'project_discipline' => $document->discipline ?: '—',
            'modified_date' => format_model_datetime($document, 'updated_at'),
            'modified_by' => $document->modifiedBy?->username ?? '—',
            'created_date' => format_model_datetime($document, 'created_at'),
            'created_by' => $createdByUsers->get($document->id)?->username ?? '—',
            'file_size' => $this->formatFileSize($fileSizeBytes),
            'item_child_count' => '0',
            'folder_child_count' => '0',
            'can_restore' => $document->trashed(),
            'document_id' => (string) $document->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  Collection<int, Project>  $projectsById
     * @return array<string, string>
     */
    protected function buildMissingDocumentRow(UserActivity $activity, array $props, Collection $projectsById): array
    {
        $fileName = (string) ($props['file_name'] ?? '—');
        $projectId = (int) ($props['project_id'] ?? 0);
        $project = $projectId > 0 ? $projectsById->get($projectId) : null;

        return [
            'file_type' => $this->fileTypeFromName($fileName),
            'file_name' => $fileName !== '' ? $fileName : '—',
            'date' => format_model_datetime($activity, 'created_at'),
            'reference_no' => $this->propOrDash($props, 'reference_no'),
            'subject' => $this->propOrDash($props, 'subject'),
            'project_number' => $this->firstNonEmpty(
                $this->propValue($props, 'project_number'),
                $project?->project_number
            ),
            'project_name' => $this->firstNonEmpty(
                $this->propValue($props, 'project_name'),
                $project?->project_name
            ),
            'project_client' => $this->firstNonEmpty(
                $this->propValue($props, 'project_client'),
                $project?->client_name
            ),
            'project_consultant' => $this->firstNonEmpty(
                $this->propValue($props, 'project_consultant'),
                $project?->consultant
            ),
            'project_discipline' => $this->propOrDash($props, 'project_discipline'),
            'modified_date' => $this->propOrDash($props, 'modified_date'),
            'modified_by' => $this->propOrDash($props, 'modified_by'),
            'created_date' => $this->propOrDash($props, 'created_date'),
            'created_by' => $this->propOrDash($props, 'created_by'),
            'file_size' => $this->propOrDash($props, 'file_size'),
            'item_child_count' => '0',
            'folder_child_count' => '0',
            'can_restore' => false,
            'document_id' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function propValue(array $props, string $key): ?string
    {
        $value = trim((string) ($props[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function propOrDash(array $props, string $key): string
    {
        return $this->propValue($props, $key) ?? '—';
    }

    protected function firstNonEmpty(?string ...$values): string
    {
        foreach ($values as $value) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '—';
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
