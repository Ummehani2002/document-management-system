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
            $this->writeCsvRow($handle, ['Date from', $report['dateFrom'] ?? 'All dates']);
            $this->writeCsvRow($handle, ['Date to', $report['dateTo'] ?? 'All dates']);
            $this->writeCsvRow($handle, ['Total documents', $report['totalDocuments']]);
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
        $activeTab = (string) $request->query('tab', 'entity');
        if (! in_array($activeTab, ['entity', 'project', 'category'], true)) {
            $activeTab = 'entity';
        }

        $dateFrom = $this->parseDate($request->query('date_from'));
        $dateTo = $this->parseDate($request->query('date_to'));

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $baseQuery = $this->scopedDocuments($request);
        if ($entityId > 0) {
            $baseQuery->where('documents.entity_id', $entityId);
        }
        $this->applyDateFilter($baseQuery, $dateFrom, $dateTo);

        $totalDocuments = (clone $baseQuery)->count();
        $entityNames = Entity::query()->pluck('name', 'id');

        $byEntity = (clone $baseQuery)
            ->selectRaw('documents.entity_id, count(*) as total')
            ->groupBy('documents.entity_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $entityNames[$row->entity_id] ?? 'Unknown',
                'total' => (int) $row->total,
            ]);

        $byProjectQuery = (clone $baseQuery)
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

        $byProject = $byProject->map(function ($row) use ($projectLabels) {
            $project = $projectLabels->get($row->project_id);

            return [
                'label' => $project
                    ? trim($project->project_number.' — '.$project->project_name)
                    : 'Unknown project',
                'total' => (int) $row->total,
            ];
        });

        $byCategoryQuery = (clone $baseQuery)
            ->selectRaw("coalesce(nullif(trim(document_type), ''), 'Uncategorized') as label, count(*) as total")
            ->groupBy('label')
            ->orderByDesc('total');

        if (! $forExport) {
            $byCategoryQuery->limit(20);
        }

        $byCategory = $byCategoryQuery->get();

        $byMainFolder = (clone $baseQuery)
            ->select('document_type')
            ->get()
            ->groupBy(fn (Document $document) => DocumentFilenameParser::mainFolderForDocumentType($document->document_type) ?? 'Other')
            ->map(fn ($rows, $label) => ['label' => $label, 'total' => $rows->count()])
            ->sortByDesc('total')
            ->values();

        $entities = Entity::query()->orderBy('name')->get(['id', 'name']);

        return [
            'totalDocuments' => $totalDocuments,
            'byEntity' => $byEntity,
            'byProject' => $byProject,
            'byCategory' => $byCategory,
            'byMainFolder' => $byMainFolder,
            'entities' => $entities,
            'selectedEntityId' => $entityId,
            'activeTab' => $activeTab,
            'dateFrom' => $dateFrom?->toDateString(),
            'dateTo' => $dateTo?->toDateString(),
        ];
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
