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
        <form id="dash-filter-form" method="GET" action="{{ route('summary-dashboard') }}" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <input type="hidden" name="tab" id="filter_tab" value="{{ $activeTab }}">
            <input type="hidden" name="entity_id" id="entity_id" value="{{ (int) $selectedEntityId > 0 ? (int) $selectedEntityId : '' }}">
            <input type="hidden" name="project_id" id="project_id" value="{{ (int) $selectedProjectId > 0 ? (int) $selectedProjectId : '' }}">
            <input type="hidden" name="main_folder" id="main_folder" value="{{ $selectedMainFolder }}">
            <input type="hidden" name="document_type" id="document_type" value="{{ $selectedDocumentType }}">
            <div>
                <label for="date_from" style="margin-bottom:6px;">From date</label>
                <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}">
            </div>
            <div>
                <label for="date_to" style="margin-bottom:6px;">To date</label>
                <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}">
            </div>
            <button type="submit">Apply dates</button>
            <a href="{{ route('summary-dashboard', ['tab' => $activeTab]) }}" style="padding:10px 14px; color:#475569; text-decoration:none;">Reset</a>
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
                        {{ number_format($entityTabTotal) }} document(s) across {{ number_format($byEntity->count()) }} entit{{ $byEntity->count() === 1 ? 'y' : 'ies' }}.
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
            <div class="dash-panel-filters">
                <div style="min-width: 220px;">
                    <label for="project_entity_select" style="margin-bottom:6px;">Entity</label>
                    <select id="project_entity_select" style="margin:0; min-width:220px;">
                        <option value="">All entities</option>
                        @foreach($entities as $entity)
                            <option value="{{ $entity->id }}" {{ (int) $selectedEntityId === (int) $entity->id ? 'selected' : '' }}>
                                {{ $entity->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <p style="margin:0; color:#64748b; font-size:0.88rem;">Choose an entity to narrow this chart, or leave as all entities.</p>
            </div>
            <div class="dash-panel-head">
                <div>
                    <h3 style="margin:0;">Project-wise report</h3>
                    <p style="margin:6px 0 0; color:#64748b; font-size:0.92rem;">
                        {{ number_format($projectTabTotal) }} document(s) across {{ number_format($byProject->count()) }} project(s).
                        @if((int) $selectedEntityId > 0)
                            <span>Filtered by {{ $entities->firstWhere('id', $selectedEntityId)?->name }}.</span>
                        @endif
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
            <div class="dash-panel-filters">
                <div style="min-width: 220px;">
                    <label for="category_entity_select" style="margin-bottom:6px;">Entity</label>
                    <select id="category_entity_select" style="margin:0; min-width:220px;">
                        <option value="">All entities</option>
                        @foreach($entities as $entity)
                            <option value="{{ $entity->id }}" {{ (int) $selectedEntityId === (int) $entity->id ? 'selected' : '' }}>
                                {{ $entity->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 280px;">
                    <label for="category_project_select" style="margin-bottom:6px;">Project</label>
                    <select id="category_project_select" style="margin:0; min-width:280px;" {{ (int) $selectedEntityId <= 0 ? 'disabled' : '' }}>
                        <option value="">All projects</option>
                        @foreach($filterProjects as $project)
                            <option value="{{ $project->id }}" {{ (int) $selectedProjectId === (int) $project->id ? 'selected' : '' }}>
                                {{ trim($project->project_number.' — '.$project->project_name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 240px;">
                    <label for="category_main_folder_select" style="margin-bottom:6px;">Category</label>
                    <select id="category_main_folder_select" style="margin:0; min-width:240px;">
                        <option value="">All categories</option>
                        @foreach(array_keys($folderTree) as $mainFolderName)
                            <option value="{{ $mainFolderName }}" {{ $selectedMainFolder === $mainFolderName ? 'selected' : '' }}>
                                {{ $mainFolderName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 240px;">
                    <label for="category_document_type_select" style="margin-bottom:6px;">Folder</label>
                    <select id="category_document_type_select" style="margin:0; min-width:240px;" {{ $selectedMainFolder === '' ? 'disabled' : '' }}>
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
            </div>
            <div class="dash-panel-head">
                <div>
                    <h3 style="margin:0;">Category-wise report</h3>
                    <p style="margin:6px 0 0; color:#64748b; font-size:0.92rem;">
                        {{ number_format($categoryTabTotal) }} document(s) in {{ number_format($byCategory->count()) }} categor{{ $byCategory->count() === 1 ? 'y' : 'ies' }}.
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

        .dash-panel-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
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
                button.addEventListener('click', () => {
                    const tab = button.dataset.tab;
                    document.getElementById('filter_tab').value = tab;

                    if (tab === 'entity') {
                        document.getElementById('entity_id').value = '';
                        document.getElementById('project_id').value = '';
                        document.getElementById('main_folder').value = '';
                        document.getElementById('document_type').value = '';
                        document.getElementById('dash-filter-form').submit();
                        return;
                    }

                    if (tab === 'project') {
                        const projectEntitySelect = document.getElementById('project_entity_select');
                        document.getElementById('entity_id').value = projectEntitySelect ? projectEntitySelect.value : '';
                        document.getElementById('project_id').value = '';
                        document.getElementById('main_folder').value = '';
                        document.getElementById('document_type').value = '';
                        document.getElementById('dash-filter-form').submit();
                        return;
                    }

                    if (tab === 'category') {
                        const categoryEntitySelect = document.getElementById('category_entity_select');
                        const categoryProjectSelect = document.getElementById('category_project_select');
                        const categoryMainFolderSelect = document.getElementById('category_main_folder_select');
                        const categoryDocumentTypeSelect = document.getElementById('category_document_type_select');
                        document.getElementById('entity_id').value = categoryEntitySelect ? categoryEntitySelect.value : '';
                        document.getElementById('project_id').value = categoryProjectSelect ? categoryProjectSelect.value : '';
                        document.getElementById('main_folder').value = categoryMainFolderSelect ? categoryMainFolderSelect.value : '';
                        document.getElementById('document_type').value = categoryDocumentTypeSelect ? categoryDocumentTypeSelect.value : '';
                        document.getElementById('dash-filter-form').submit();
                        return;
                    }

                    switchTab(tab);
                });
            });

            switchTab(activeTab);
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var projectsByEntity = @json($projectsByEntity);
            var folderTree = @json($folderTree);
            var filterForm = document.getElementById('dash-filter-form');
            var entityInput = document.getElementById('entity_id');
            var projectInput = document.getElementById('project_id');
            var mainFolderInput = document.getElementById('main_folder');
            var documentTypeInput = document.getElementById('document_type');
            var tabInput = document.getElementById('filter_tab');

            var projectEntitySelect = document.getElementById('project_entity_select');
            var categoryEntitySelect = document.getElementById('category_entity_select');
            var categoryProjectSelect = document.getElementById('category_project_select');
            var categoryMainFolderSelect = document.getElementById('category_main_folder_select');
            var categoryDocumentTypeSelect = document.getElementById('category_document_type_select');

            function submitTabFilters(tab) {
                tabInput.value = tab;
                filterForm.submit();
            }

            function syncHiddenFromCategoryFilters() {
                entityInput.value = categoryEntitySelect ? categoryEntitySelect.value : '';
                projectInput.value = categoryProjectSelect ? categoryProjectSelect.value : '';
                mainFolderInput.value = categoryMainFolderSelect ? categoryMainFolderSelect.value : '';
                documentTypeInput.value = categoryDocumentTypeSelect ? categoryDocumentTypeSelect.value : '';
            }

            function renderCategoryProjects() {
                if (!categoryProjectSelect || !categoryEntitySelect) return;

                var entityId = categoryEntitySelect.value;
                var projects = projectsByEntity[entityId] || [];
                var currentProjectId = projectInput.value;
                categoryProjectSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'All projects';
                categoryProjectSelect.appendChild(placeholder);

                if (!entityId) {
                    categoryProjectSelect.disabled = true;
                    return;
                }

                categoryProjectSelect.disabled = false;
                projects.forEach(function (project) {
                    var option = document.createElement('option');
                    option.value = String(project.id);
                    option.textContent = project.label;
                    if (String(project.id) === String(currentProjectId)) {
                        option.selected = true;
                    }
                    categoryProjectSelect.appendChild(option);
                });
            }

            function renderCategoryFolders() {
                if (!categoryDocumentTypeSelect || !categoryMainFolderSelect) return;

                var mainFolder = categoryMainFolderSelect.value;
                var currentType = documentTypeInput.value;
                categoryDocumentTypeSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'All folders';
                categoryDocumentTypeSelect.appendChild(placeholder);

                if (!mainFolder) {
                    categoryDocumentTypeSelect.disabled = true;
                    return;
                }

                categoryDocumentTypeSelect.disabled = false;
                (folderTree[mainFolder] || []).forEach(function (subfolder) {
                    var option = document.createElement('option');
                    option.value = subfolder;
                    option.textContent = subfolder;
                    if (subfolder === currentType) {
                        option.selected = true;
                    }
                    categoryDocumentTypeSelect.appendChild(option);
                });
            }

            if (projectEntitySelect) {
                projectEntitySelect.addEventListener('change', function () {
                    entityInput.value = projectEntitySelect.value;
                    projectInput.value = '';
                    mainFolderInput.value = '';
                    documentTypeInput.value = '';
                    submitTabFilters('project');
                });
            }

            if (categoryEntitySelect) {
                categoryEntitySelect.addEventListener('change', function () {
                    projectInput.value = '';
                    documentTypeInput.value = '';
                    renderCategoryProjects();
                    syncHiddenFromCategoryFilters();
                    submitTabFilters('category');
                });
            }

            if (categoryProjectSelect) {
                categoryProjectSelect.addEventListener('change', function () {
                    syncHiddenFromCategoryFilters();
                    submitTabFilters('category');
                });
            }

            if (categoryMainFolderSelect) {
                categoryMainFolderSelect.addEventListener('change', function () {
                    documentTypeInput.value = '';
                    renderCategoryFolders();
                    syncHiddenFromCategoryFilters();
                    submitTabFilters('category');
                });
            }

            if (categoryDocumentTypeSelect) {
                categoryDocumentTypeSelect.addEventListener('change', function () {
                    syncHiddenFromCategoryFilters();
                    submitTabFilters('category');
                });
            }

            renderCategoryProjects();
            renderCategoryFolders();
        });
    </script>
@endsection
