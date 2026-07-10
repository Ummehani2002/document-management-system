@extends('layouts.app')

@section('content')
    @php
        $activeTab = in_array($activeTab ?? 'entity', ['entity', 'project', 'category'], true) ? $activeTab : 'entity';
        $entityRows = $byEntity->map(fn ($row) => ['label' => $row->label, 'total' => $row->total]);
        $categoryRows = $byCategory->map(fn ($row) => ['label' => $row->label, 'total' => $row->total]);
    @endphp

    <h2>Dashboard</h2>
    <p style="color:#64748b; margin-top:-6px; margin-bottom:18px;">
        Summary reports — entity, project, and category wise document counts.
    </p>

    <div class="card" style="padding: 18px 20px; margin-bottom: 18px;">
        <form method="GET" action="{{ route('summary-dashboard') }}" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <input type="hidden" name="tab" id="filter_tab" value="{{ $activeTab }}">
            <div style="min-width: 220px;">
                <label for="entity_id" style="margin-bottom:6px;">Entity</label>
                <select id="entity_id" name="entity_id" style="margin:0;">
                    <option value="">All entities</option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" {{ (int) $selectedEntityId === (int) $entity->id ? 'selected' : '' }}>
                            {{ $entity->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width: 280px;">
                <label for="project_id" style="margin-bottom:6px;">Project</label>
                <select id="project_id" name="project_id" style="margin:0;" {{ (int) $selectedEntityId <= 0 ? 'disabled' : '' }}>
                    <option value="">All projects</option>
                    @foreach($filterProjects as $project)
                        <option value="{{ $project->id }}" {{ (int) $selectedProjectId === (int) $project->id ? 'selected' : '' }}>
                            {{ trim($project->project_number.' — '.$project->project_name) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width: 240px;">
                <label for="main_folder" style="margin-bottom:6px;">Category</label>
                <select id="main_folder" name="main_folder" style="margin:0;">
                    <option value="">All categories</option>
                    @foreach(array_keys($folderTree) as $mainFolderName)
                        <option value="{{ $mainFolderName }}" {{ $selectedMainFolder === $mainFolderName ? 'selected' : '' }}>
                            {{ $mainFolderName }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width: 240px;">
                <label for="document_type" style="margin-bottom:6px;">Folder</label>
                <select id="document_type" name="document_type" style="margin:0;" {{ $selectedMainFolder === '' ? 'disabled' : '' }}>
                    <option value="">All folders</option>
                    @if($selectedMainFolder !== '')
                        @foreach($folderTree[$selectedMainFolder] ?? [] as $subfolder)
                            <option value="{{ $subfolder }}" {{ $selectedDocumentType === $subfolder ? 'selected' : '' }}>
                                {{ $subfolder }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div>
                <label for="date_from" style="margin-bottom:6px;">From date</label>
                <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}">
            </div>
            <div>
                <label for="date_to" style="margin-bottom:6px;">To date</label>
                <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}">
            </div>
            <button type="submit">Apply filters</button>
            <a href="{{ route('summary-dashboard') }}" style="padding:10px 14px; color:#475569; text-decoration:none;">Reset</a>
        </form>
    </div>

    <div class="card" style="padding:0; overflow:hidden; margin-bottom:18px;">
        <div class="dash-tabs" role="tablist" aria-label="Dashboard reports">
            <button type="button" class="dash-tab {{ $activeTab === 'entity' ? 'is-active' : '' }}" data-tab="entity" role="tab" aria-selected="{{ $activeTab === 'entity' ? 'true' : 'false' }}">
                Entity-wise
            </button>
            <button type="button" class="dash-tab {{ $activeTab === 'project' ? 'is-active' : '' }}" data-tab="project" role="tab" aria-selected="{{ $activeTab === 'project' ? 'true' : 'false' }}">
                Project-wise
            </button>
            <button type="button" class="dash-tab {{ $activeTab === 'category' ? 'is-active' : '' }}" data-tab="category" role="tab" aria-selected="{{ $activeTab === 'category' ? 'true' : 'false' }}">
                Category-wise
            </button>
        </div>

        <div class="dash-tab-panel {{ $activeTab === 'entity' ? 'is-active' : '' }}" data-panel="entity" role="tabpanel">
            <div class="dash-panel-head">
                <div>
                    <h3 style="margin:0;">Entity-wise report</h3>
                    <p style="margin:6px 0 0; color:#64748b; font-size:0.92rem;">
                        {{ number_format($totalDocuments) }} document(s) across {{ number_format($byEntity->count()) }} entit{{ $byEntity->count() === 1 ? 'y' : 'ies' }}.
                    </p>
                </div>
                @include('summary-dashboard._download-button', ['tab' => 'entity'])
            </div>
            <div class="dash-chart-wrap" style="height:320px;">
                <canvas id="entityChart"></canvas>
            </div>
            @include('summary-dashboard._breakdown-table', ['title' => 'Entity breakdown', 'rows' => $entityRows])
        </div>

        <div class="dash-tab-panel {{ $activeTab === 'project' ? 'is-active' : '' }}" data-panel="project" role="tabpanel">
            <div class="dash-panel-head">
                <div>
                    <h3 style="margin:0;">Project-wise report</h3>
                    <p style="margin:6px 0 0; color:#64748b; font-size:0.92rem;">
                        {{ number_format($totalDocuments) }} document(s) across {{ number_format($byProject->count()) }} project(s).
                    </p>
                </div>
                @include('summary-dashboard._download-button', ['tab' => 'project'])
            </div>
            <div class="dash-chart-wrap" style="height:{{ max(280, min(640, $byProject->count() * 28)) }}px;">
                <canvas id="projectChart"></canvas>
            </div>
            @include('summary-dashboard._breakdown-table', ['title' => 'Project breakdown', 'rows' => $byProject])
        </div>

        <div class="dash-tab-panel {{ $activeTab === 'category' ? 'is-active' : '' }}" data-panel="category" role="tabpanel">
            <div class="dash-panel-head">
                <div>
                    <h3 style="margin:0;">Category-wise report</h3>
                    <p style="margin:6px 0 0; color:#64748b; font-size:0.92rem;">
                        {{ number_format($totalDocuments) }} document(s) in {{ number_format($byCategory->count()) }} categor{{ $byCategory->count() === 1 ? 'y' : 'ies' }}.
                    </p>
                </div>
                @include('summary-dashboard._download-button', ['tab' => 'category'])
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:18px;">
                <div class="dash-chart-wrap" style="height:360px;">
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="dash-chart-wrap" style="height:360px;">
                    <canvas id="mainFolderChart"></canvas>
                </div>
            </div>
            @include('summary-dashboard._breakdown-table', ['title' => 'Category breakdown', 'rows' => $categoryRows])
        </div>
    </div>

    <style>
        .dash-tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 0 12px;
        }

        .dash-tab {
            appearance: none;
            border: none;
            background: transparent;
            color: #64748b;
            font: inherit;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 14px 18px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
        }

        .dash-tab:hover {
            color: #212d3e;
        }

        .dash-tab.is-active {
            color: #212d3e;
            border-bottom-color: #c4a47c;
            background: #fff;
        }

        .dash-tab-panel {
            display: none;
            padding: 20px;
        }

        .dash-tab-panel.is-active {
            display: block;
        }

        .dash-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .dash-download-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            border-radius: 8px;
            background: #212d3e;
            color: #fff;
            text-decoration: none;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .dash-download-btn:hover {
            background: #2d3a52;
            color: #fff;
        }

        .dash-chart-wrap {
            position: relative;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const palette = ['#212d3e', '#c4a47c', '#2d3a52', '#a88962', '#475569', '#64748b', '#94a3b8', '#1e293b', '#d4b896', '#334155'];
            const chartFont = { family: '"Segoe UI", system-ui, sans-serif' };
            const charts = {};
            let activeTab = @json($activeTab);

            const entityData = @json($entityRows->values());
            const projectData = @json($byProject->values());
            const categoryData = @json($categoryRows->values());
            const mainFolderData = @json($byMainFolder->values());

            function chartCount(ctx) {
                const parsed = ctx.parsed;
                if (parsed != null && typeof parsed === 'object') {
                    if (ctx.chart.options.indexAxis === 'y') {
                        return parsed.x ?? ctx.raw;
                    }

                    return parsed.y ?? parsed.x ?? ctx.raw;
                }

                return parsed ?? ctx.raw;
            }

            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { font: chartFont, color: '#334155' } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                return ` ${Number(chartCount(ctx)).toLocaleString()} document(s)`;
                            },
                        },
                    },
                },
            };

            function initTabCharts(tab) {
                if (tab === 'entity' && !charts.entity) {
                    charts.entity = new Chart(document.getElementById('entityChart'), {
                        type: 'bar',
                        data: {
                            labels: entityData.map((row) => row.label),
                            datasets: [{
                                label: 'Documents',
                                data: entityData.map((row) => row.total),
                                backgroundColor: palette,
                                borderRadius: 6,
                            }],
                        },
                        options: {
                            ...commonOptions,
                            indexAxis: 'y',
                            scales: {
                                x: { beginAtZero: true, ticks: { precision: 0, font: chartFont } },
                                y: { ticks: { font: chartFont } },
                            },
                        },
                    });
                }

                if (tab === 'project' && !charts.project) {
                    charts.project = new Chart(document.getElementById('projectChart'), {
                        type: 'bar',
                        data: {
                            labels: projectData.map((row) => row.label),
                            datasets: [{
                                label: 'Documents',
                                data: projectData.map((row) => row.total),
                                backgroundColor: '#c4a47c',
                                borderRadius: 6,
                            }],
                        },
                        options: {
                            ...commonOptions,
                            indexAxis: 'y',
                            scales: {
                                x: { beginAtZero: true, ticks: { precision: 0, font: chartFont } },
                                y: { ticks: { font: chartFont, autoSkip: false } },
                            },
                        },
                    });
                }

                if (tab === 'category' && !charts.category) {
                    charts.category = new Chart(document.getElementById('categoryChart'), {
                        type: 'bar',
                        data: {
                            labels: categoryData.map((row) => row.label),
                            datasets: [{
                                label: 'Documents',
                                data: categoryData.map((row) => row.total),
                                backgroundColor: palette,
                                borderRadius: 6,
                            }],
                        },
                        options: {
                            ...commonOptions,
                            scales: {
                                x: {
                                    ticks: {
                                        maxRotation: 60,
                                        minRotation: 30,
                                        font: chartFont,
                                        callback: function (value) {
                                            const label = this.getLabelForValue(value) || '';
                                            return label.length > 22 ? label.slice(0, 22) + '…' : label;
                                        },
                                    },
                                },
                                y: { beginAtZero: true, ticks: { precision: 0, font: chartFont } },
                            },
                        },
                    });
                    charts.mainFolder = new Chart(document.getElementById('mainFolderChart'), {
                        type: 'doughnut',
                        data: {
                            labels: mainFolderData.map((row) => row.label),
                            datasets: [{
                                data: mainFolderData.map((row) => row.total),
                                backgroundColor: palette,
                                borderWidth: 2,
                                borderColor: '#fff',
                            }],
                        },
                        options: {
                            ...commonOptions,
                            plugins: {
                                ...commonOptions.plugins,
                                legend: { position: 'bottom', labels: { font: chartFont, color: '#334155' } },
                            },
                        },
                    });
                }

                Object.values(charts).forEach((chart) => chart.resize());
            }

            function switchTab(tab) {
                activeTab = tab;
                document.getElementById('filter_tab').value = tab;
                document.querySelectorAll('.dash-tab').forEach((button) => {
                    const isActive = button.dataset.tab === tab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                document.querySelectorAll('.dash-tab-panel').forEach((panel) => {
                    panel.classList.toggle('is-active', panel.dataset.panel === tab);
                });
                initTabCharts(tab);
            }

            document.querySelectorAll('.dash-tab').forEach((button) => {
                button.addEventListener('click', () => switchTab(button.dataset.tab));
            });

            switchTab(activeTab);
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var projectsByEntity = @json($projectsByEntity);
            var folderTree = @json($folderTree);
            var entitySelect = document.getElementById('entity_id');
            var projectSelect = document.getElementById('project_id');
            var mainFolderSelect = document.getElementById('main_folder');
            var documentTypeSelect = document.getElementById('document_type');
            var selectedProjectId = @json((int) $selectedProjectId);
            var selectedDocumentType = @json($selectedDocumentType);

            function renderProjects() {
                if (!projectSelect || !entitySelect) return;

                var entityId = entitySelect.value;
                var projects = projectsByEntity[entityId] || [];
                projectSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'All projects';
                projectSelect.appendChild(placeholder);

                if (!entityId) {
                    projectSelect.disabled = true;
                    projectSelect.value = '';
                    return;
                }

                projectSelect.disabled = false;
                projects.forEach(function (project) {
                    var option = document.createElement('option');
                    option.value = String(project.id);
                    option.textContent = project.label;
                    if (Number(project.id) === Number(selectedProjectId)) {
                        option.selected = true;
                    }
                    projectSelect.appendChild(option);
                });

                if (selectedProjectId) {
                    projectSelect.value = String(selectedProjectId);
                }
            }

            function renderFolders() {
                if (!documentTypeSelect || !mainFolderSelect) return;

                var mainFolder = mainFolderSelect.value;
                documentTypeSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'All folders';
                documentTypeSelect.appendChild(placeholder);

                if (!mainFolder) {
                    documentTypeSelect.disabled = true;
                    documentTypeSelect.value = '';
                    return;
                }

                documentTypeSelect.disabled = false;
                (folderTree[mainFolder] || []).forEach(function (subfolder) {
                    var option = document.createElement('option');
                    option.value = subfolder;
                    option.textContent = subfolder;
                    if (subfolder === selectedDocumentType) {
                        option.selected = true;
                    }
                    documentTypeSelect.appendChild(option);
                });

                if (selectedDocumentType) {
                    documentTypeSelect.value = selectedDocumentType;
                }
            }

            if (entitySelect) {
                entitySelect.addEventListener('change', function () {
                    selectedProjectId = 0;
                    renderProjects();
                });
            }

            if (mainFolderSelect) {
                mainFolderSelect.addEventListener('change', function () {
                    selectedDocumentType = '';
                    renderFolders();
                });
            }

            renderProjects();
            renderFolders();
        });
    </script>
@endsection
