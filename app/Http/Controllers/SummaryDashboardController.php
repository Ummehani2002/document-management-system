<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Project;
use App\Services\DocumentAccessService;
use App\Services\DocumentFilenameParser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SummaryDashboardController extends Controller
{
    public function __construct(
        protected DocumentAccessService $access
    ) {}

    public function index(Request $request): View
    {
        $report = $this->buildReport($request);

        return view('summary-dashboard.index', $report);
    }

    public function download(Request $request): StreamedResponse
    {
        $report = $this->buildReport($request, forExport: true);
        $tab = $report['activeTab'];
        $filename = sprintf('dashboard-%s-report-%s.csv', $tab, now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($report, $tab): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeCsvRow($handle, ['Tanseeq DMS — Dashboard report']);
            $this->writeCsvRow($handle, ['Report type', ucfirst($tab).' wise']);
            $this->writeCsvRow($handle, ['Generated at', now()->timezone(config('app.timezone', 'Asia/Dubai'))->format('Y-m-d H:i')]);
            $this->writeCsvRow($handle, ['Entity filter', $this->entityFilterLabel($report)]);
            $this->writeCsvRow($handle, ['Project filter', $this->projectFilterLabel($report)]);
            $this->writeCsvRow($handle, ['Category filter', $report['selectedMainFolder'] !== '' ? $report['selectedMainFolder'] : 'All categories']);
            $this->writeCsvRow($handle, ['Folder filter', $report['selectedDocumentType'] !== '' ? $report['selectedDocumentType'] : 'All folders']);
            $this->writeCsvRow($handle, ['Date from', $report['dateFrom'] ?? 'All dates']);
            $this->writeCsvRow($handle, ['Date to', $report['dateTo'] ?? 'All dates']);
            $tabTotal = match ($tab) {
                'project' => $report['projectTabTotal'],
                'category' => $report['categoryTabTotal'],
                default => $report['entityTabTotal'],
            };
            $this->writeCsvRow($handle, ['Total documents', $tabTotal]);
            $this->writeCsvRow($handle, []);

            if ($tab === 'entity') {
                $this->writeCsvRow($handle, ['Entity', 'Documents']);
                foreach ($report['byEntity'] as $row) {
                    $this->writeCsvRow($handle, [$row->label, $row->total]);
                }
            }

            if ($tab === 'project') {
                $this->writeCsvRow($handle, ['Project', 'Documents']);
                foreach ($report['byProject'] as $row) {
                    $this->writeCsvRow($handle, [$row['label'], $row['total']]);
                }
            }

            if ($tab === 'category') {
                $this->writeCsvRow($handle, ['Category', 'Documents']);
                foreach ($report['byCategory'] as $row) {
                    $this->writeCsvRow($handle, [$row->label, $row->total]);
                }

                $this->writeCsvRow($handle, []);
                $this->writeCsvRow($handle, ['Main folder', 'Documents']);
                foreach ($report['byMainFolder'] as $row) {
                    $this->writeCsvRow($handle, [$row['label'], $row['total']]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(Request $request, bool $forExport = false): array
    {
        $entityId = (int) $request->query('entity_id', 0);
        $projectId = (int) $request->query('project_id', 0);
        $mainFolder = trim((string) $request->query('main_folder', ''));
        $documentType = trim((string) $request->query('document_type', ''));
        $activeTab = (string) $request->query('tab', 'entity');
        if (! in_array($activeTab, ['entity', 'project', 'category'], true)) {
            $activeTab = 'entity';
        }

        $folderTree = DocumentFilenameParser::sidebarFolderTree();
        if ($mainFolder !== '' && ! array_key_exists($mainFolder, $folderTree)) {
            $mainFolder = '';
        }
        if ($documentType !== '') {
            $validTypes = $mainFolder !== ''
                ? ($folderTree[$mainFolder] ?? [])
                : array_merge(...array_values($folderTree));
            if (! in_array($documentType, $validTypes, true)) {
                $documentType = '';
            }
        }

        $dateFrom = $this->parseDate($request->query('date_from'));
        $dateTo = $this->parseDate($request->query('date_to'));

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $entities = $this->accessibleEntitiesWithProjects($request);
        if ($entityId > 0 && ! $entities->contains('id', $entityId)) {
            $entityId = 0;
            $projectId = 0;
        }

        if ($projectId > 0) {
            $project = Project::query()->find($projectId);
            if ($project === null) {
                $projectId = 0;
            } elseif ($entityId > 0 && (int) $project->entity_id !== $entityId) {
                $projectId = 0;
            } elseif ($entityId === 0) {
                $entityId = (int) $project->entity_id;
            }
        }

        $filterProjects = $this->projectsForEntity($entities, $entityId, $request->user());
        if ($projectId > 0 && ! $filterProjects->contains('id', $projectId)) {
            $projectId = 0;
        }

        $scopedQuery = $this->scopedDocuments($request);
        $this->applyDateFilter($scopedQuery, $dateFrom, $dateTo);

        $entityQuery = clone $scopedQuery;
        $byEntity = $this->aggregateByEntity($entityQuery);
        $entityTabTotal = (clone $entityQuery)->count();

        $projectQuery = clone $scopedQuery;
        if ($entityId > 0) {
            $projectQuery->where('documents.entity_id', $entityId);
        }
        $byProject = $this->aggregateByProject($projectQuery, $forExport);
        $projectTabTotal = (clone $projectQuery)->count();

        $categoryQuery = clone $scopedQuery;
        if ($entityId > 0) {
            $categoryQuery->where('documents.entity_id', $entityId);
        }
        if ($projectId > 0) {
            $categoryQuery->where('documents.project_id', $projectId);
        }
        $this->applyFolderFilter($categoryQuery, $folderTree, $mainFolder, $documentType);
        $this->excludeUnclassifiedDocuments($categoryQuery);
        $byCategory = $this->aggregateByCategory($categoryQuery, $forExport);
        $byMainFolder = $this->aggregateByMainFolder($categoryQuery);
        $categoryTabTotal = (clone $categoryQuery)->count();

        $projectsByEntity = $entities->mapWithKeys(function ($entity) use ($entities, $request) {
            return [
                $entity->id => $this->projectsForEntity($entities, (int) $entity->id, $request->user())
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'label' => trim($project->project_number.' — '.$project->project_name),
                        'project_manager' => (string) ($project->project_manager ?? ''),
                        'document_controller' => (string) ($project->document_controller ?? ''),
                    ])
                    ->values(),
            ];
        });

        $selectedProject = $filterProjects->firstWhere('id', $projectId);

        return [
            'entityTabTotal' => $entityTabTotal,
            'projectTabTotal' => $projectTabTotal,
            'categoryTabTotal' => $categoryTabTotal,
            'byEntity' => $byEntity,
            'byProject' => $byProject,
            'byCategory' => $byCategory,
            'byMainFolder' => $byMainFolder,
            'entities' => $entities,
            'filterProjects' => $filterProjects,
            'projectsByEntity' => $projectsByEntity,
            'folderTree' => $folderTree,
            'selectedEntityId' => $entityId,
            'selectedProjectId' => $projectId,
            'selectedProjectManager' => (string) ($selectedProject?->project_manager ?? ''),
            'selectedDocumentController' => (string) ($selectedProject?->document_controller ?? ''),
            'selectedMainFolder' => $mainFolder,
            'selectedDocumentType' => $documentType,
            'activeTab' => $activeTab,
            'dateFrom' => $dateFrom?->toDateString(),
            'dateTo' => $dateTo?->toDateString(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Entity>
     */
    private function accessibleEntitiesWithProjects(Request $request)
    {
        $user = $request->user();
        $query = Entity::query()
            ->with(['projects' => fn ($builder) => $builder->orderBy('project_number')])
            ->orderBy('name');

        if (! $this->access->isAdmin($user)) {
            $entityIds = $this->access->accessibleEntityIds($user);
            if ($entityIds !== []) {
                $query->whereIn('id', $entityIds);
            }
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Entity>  $entities
     * @return \Illuminate\Support\Collection<int, Project>
     */
    private function projectsForEntity($entities, int $entityId, ?\App\Models\User $user)
    {
        if ($entityId <= 0) {
            return collect();
        }

        $entity = $entities->firstWhere('id', $entityId);
        if ($entity === null) {
            return collect();
        }

        $projects = $entity->projects;
        if ($user !== null && ! $this->access->isAdmin($user)) {
            $restrictedEntityIds = $this->access->entitiesWithProjectRestrictions($user);
            if (in_array($entityId, $restrictedEntityIds, true)) {
                $allowedProjectIds = $this->access->allowedProjectIdsForEntity($user, $entityId);
                $projects = $projects->whereIn('id', $allowedProjectIds)->values();
            }
        }

        return $projects;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{label: string, total: int}>
     */
    private function aggregateByEntity(Builder $query)
    {
        $entityNames = Entity::query()->pluck('name', 'id');

        return (clone $query)
            ->selectRaw('documents.entity_id, count(*) as total')
            ->groupBy('documents.entity_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $entityNames[$row->entity_id] ?? 'Unknown',
                'total' => (int) $row->total,
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{label: string, total: int}>
     */
    private function aggregateByProject(Builder $query, bool $forExport)
    {
        $byProjectQuery = (clone $query)
            ->selectRaw('documents.project_id, count(*) as total')
            ->groupBy('documents.project_id')
            ->orderByDesc('total');

        if (! $forExport) {
            $byProjectQuery->limit(25);
        }

        $byProject = $byProjectQuery->get();
        $projectLabels = Project::query()
            ->whereIn('id', $byProject->pluck('project_id'))
            ->get(['id', 'project_number', 'project_name'])
            ->keyBy('id');

        return $byProject->map(function ($row) use ($projectLabels) {
            $project = $projectLabels->get($row->project_id);

            return [
                'label' => $project
                    ? trim($project->project_number.' — '.$project->project_name)
                    : 'Unknown project',
                'total' => (int) $row->total,
            ];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{label: string, total: int}>
     */
    private function aggregateByCategory(Builder $query, bool $forExport)
    {
        $byCategoryQuery = (clone $query)
            ->selectRaw('document_type as label, count(*) as total')
            ->groupBy('document_type')
            ->orderByDesc('total');

        if (! $forExport) {
            $byCategoryQuery->limit(20);
        }

        return $byCategoryQuery->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{label: string, total: int}>
     */
    private function aggregateByMainFolder(Builder $query)
    {
        return (clone $query)
            ->select('document_type')
            ->get()
            ->groupBy(fn (Document $document) => DocumentFilenameParser::mainFolderForDocumentType($document->document_type))
            ->filter(fn ($rows, $label) => is_string($label) && $label !== '' && strcasecmp($label, 'Other') !== 0)
            ->map(fn ($rows, $label) => ['label' => $label, 'total' => $rows->count()])
            ->sortByDesc('total')
            ->values();
    }

    private function excludeUnclassifiedDocuments(Builder $query): void
    {
        $query->whereNotNull('documents.document_type')
            ->where('documents.document_type', '!=', '')
            ->whereRaw("LOWER(TRIM(documents.document_type)) != 'other'");
    }

    /**
     * @param  array<string, list<string>>  $folderTree
     */
    private function applyFolderFilter(Builder $query, array $folderTree, string $mainFolder, string $documentType): void
    {
        if ($documentType !== '') {
            DocumentFilenameParser::applyFolderTypeFilter($query, [$documentType]);

            return;
        }

        if ($mainFolder !== '') {
            $types = $folderTree[$mainFolder] ?? [];
            if ($types !== []) {
                DocumentFilenameParser::applyFolderTypeFilter($query, $types);
            }
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $row
     */
    private function writeCsvRow($handle, array $row): void
    {
        fputcsv($handle, $row);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function entityFilterLabel(array $report): string
    {
        $entityId = (int) ($report['selectedEntityId'] ?? 0);
        if ($entityId <= 0) {
            return 'All entities';
        }

        $entity = $report['entities']->firstWhere('id', $entityId);

        return $entity?->name ?? 'Entity #'.$entityId;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function projectFilterLabel(array $report): string
    {
        $projectId = (int) ($report['selectedProjectId'] ?? 0);
        if ($projectId <= 0) {
            return 'All projects';
        }

        $project = ($report['filterProjects'] ?? collect())->firstWhere('id', $projectId);
        if ($project !== null) {
            return trim($project->project_number.' — '.$project->project_name);
        }

        $project = Project::query()->find($projectId);

        return $project
            ? trim($project->project_number.' — '.$project->project_name)
            : 'Project #'.$projectId;
    }

    private function scopedDocuments(Request $request): Builder
    {
        return Document::query()
            ->from('documents')
            ->tap(fn (Builder $query) => $this->access->scopeAccessible($query, $request->user()));
    }

    private function applyDateFilter(Builder $query, ?Carbon $dateFrom, ?Carbon $dateTo): void
    {
        if ($dateFrom) {
            $query->where('documents.created_at', '>=', $dateFrom->copy()->startOfDay()->utc());
        }

        if ($dateTo) {
            $query->where('documents.created_at', '<=', $dateTo->copy()->endOfDay()->utc());
        }
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
