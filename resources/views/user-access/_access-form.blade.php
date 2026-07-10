<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Access level</h3>
    <label>
        <select name="role" style="min-width: 220px; padding: 8px;">
            <option value="">— Limited access —</option>
            @foreach ($roles as $roleName)
                <option value="{{ $roleName }}" @selected(old('role', $user?->roles->first()?->name) === $roleName)>{{ $roleName }}</option>
            @endforeach
        </select>
    </label>
</div>

@php
    $projectsByEntity = $entities->mapWithKeys(function ($entity) {
        return [
            $entity->id => $entity->projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'label' => trim($project->project_number.' — '.$project->project_name),
                ];
            })->values(),
        ];
    });
    $initialEntityId = $entities->first(function ($entity) use ($selectedProjectIds) {
        return $entity->projects->contains(fn ($project) => in_array($project->id, $selectedProjectIds, true));
    })?->id ?? $entities->first()?->id;
@endphp

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Entity, project &amp; folder access</h3>

    @if($entities->isEmpty())
        <p>No entities yet. <a href="{{ route('entities.create') }}">Create an entity</a> first.</p>
    @else
        <div style="margin-bottom: 16px;">
            <label for="access-entity-select" style="display:block; margin-bottom:6px;">Entity</label>
            <select id="access-entity-select" style="min-width: 280px; padding: 8px;">
                @foreach ($entities as $entity)
                    <option value="{{ $entity->id }}" @selected((int) $initialEntityId === (int) $entity->id)>{{ $entity->name }}</option>
                @endforeach
            </select>
        </div>

        <div id="access-projects-panel" style="margin-bottom: 16px;">
            <label for="access-project-select" style="display:block; margin-bottom:6px;">Projects</label>
            <select id="access-project-select" style="min-width: 280px; padding: 8px;">
                <option value="">— Select project —</option>
            </select>
            <p id="access-projects-empty" style="color:#64748b; margin:8px 0 0; display:none;">No projects for this entity yet.</p>
        </div>

        <div id="access-folders-panel" style="display:none;">
            <div style="font-weight: 500; margin-bottom: 8px;">Folders <span style="color:#94a3b8; font-weight:400;">(optional)</span></div>
            <p style="color:#64748b; margin:0 0 10px; font-size:0.9rem;">
                Check a main folder to select all its subfolders. Applies to all selected projects in this entity.
                Leave everything unchecked for full project access.
            </p>
            @foreach ($folderTree as $mainFolder => $subfolders)
                <div class="access-folder-group" data-main-folder="{{ $mainFolder }}" style="margin-bottom: 12px;">
                    <label style="display:flex; align-items:center; gap:8px; margin-bottom:6px; cursor:pointer;">
                        <input
                            type="checkbox"
                            class="access-main-folder-check"
                            data-main-folder="{{ $mainFolder }}"
                        >
                        <span style="font-weight: 500;">{{ $mainFolder }}</span>
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px 12px; padding-left: 28px;">
                        @foreach ($subfolders as $sub)
                            @php $key = $mainFolder.'|'.$sub; @endphp
                            <label style="display: flex; align-items: flex-start; gap: 6px; cursor: pointer;">
                                <input
                                    type="checkbox"
                                    class="access-folder-check"
                                    data-main-folder="{{ $mainFolder }}"
                                    value="{{ $key }}"
                                >
                                <span>{{ $sub }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div id="access-grants-summary" style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
            <div style="font-weight: 500; margin-bottom: 8px;">Selected access</div>
            <div id="access-grants-summary-body" style="color:#64748b; font-size:0.9rem;"></div>
        </div>

        <div id="access-hidden-inputs"></div>
    @endif
</div>

<p style="margin-top: 20px;">
    <button type="submit" style="padding: 10px 24px; background: #212d3e; color: #fff; border: none; border-radius: 5px; cursor: pointer;">
        {{ ($isEdit ?? false) ? 'Save access' : 'Add user' }}
    </button>
</p>

@if($entities->isNotEmpty())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var projectsByEntity = @json($projectsByEntity);
        var entityNames = @json($entities->pluck('name', 'id'));
        var initialProjectIds = @json(array_values(array_map('intval', $selectedProjectIds)));
        var initialFoldersByProject = @json($selectedFoldersByProject);

        var entitySelect = document.getElementById('access-entity-select');
        var projectSelect = document.getElementById('access-project-select');
        var projectsEmpty = document.getElementById('access-projects-empty');
        var foldersPanel = document.getElementById('access-folders-panel');
        var summaryBody = document.getElementById('access-grants-summary-body');
        var hiddenInputs = document.getElementById('access-hidden-inputs');
        var folderChecks = Array.from(document.querySelectorAll('.access-folder-check'));
        var mainFolderChecks = Array.from(document.querySelectorAll('.access-main-folder-check'));

        var selectedProjects = new Set(initialProjectIds.map(String));
        var foldersByProject = {};

        Object.keys(initialFoldersByProject).forEach(function (projectId) {
            foldersByProject[projectId] = new Set(initialFoldersByProject[projectId] || []);
        });

        function currentEntityId() {
            return entitySelect ? String(entitySelect.value) : '';
        }

        function projectsForCurrentEntity() {
            return projectsByEntity[currentEntityId()] || [];
        }

        function selectedProjectsInCurrentEntity() {
            return projectsForCurrentEntity()
                .map(function (project) { return String(project.id); })
                .filter(function (projectId) { return selectedProjects.has(projectId); });
        }

        function folderUnionForProjects(projectIds) {
            var union = new Set();
            projectIds.forEach(function (projectId) {
                (foldersByProject[projectId] || new Set()).forEach(function (key) {
                    union.add(key);
                });
            });
            return union;
        }

        function syncHiddenInputs() {
            if (!hiddenInputs) return;
            hiddenInputs.innerHTML = '';

            Array.from(selectedProjects).sort().forEach(function (projectId) {
                var projectInput = document.createElement('input');
                projectInput.type = 'hidden';
                projectInput.name = 'project_ids[]';
                projectInput.value = projectId;
                hiddenInputs.appendChild(projectInput);

                (foldersByProject[projectId] || new Set()).forEach(function (key) {
                    var folderInput = document.createElement('input');
                    folderInput.type = 'hidden';
                    folderInput.name = 'folders[' + projectId + '][]';
                    folderInput.value = key;
                    hiddenInputs.appendChild(folderInput);
                });
            });
        }

        function renderSummary() {
            if (!summaryBody) return;

            if (selectedProjects.size === 0) {
                summaryBody.textContent = 'No projects selected yet.';
                return;
            }

            var lines = [];
            Object.keys(projectsByEntity).forEach(function (entityId) {
                var entityProjects = (projectsByEntity[entityId] || []).filter(function (project) {
                    return selectedProjects.has(String(project.id));
                });

                if (!entityProjects.length) return;

                var entityLabel = entityNames[entityId] || ('Entity ' + entityId);
                var projectLabels = entityProjects.map(function (project) { return project.label; }).join(', ');
                lines.push('<strong>' + entityLabel + ':</strong> ' + projectLabels);
            });

            summaryBody.innerHTML = lines.join('<br>');
        }

        function subfolderChecksForMain(mainFolder) {
            return folderChecks.filter(function (checkbox) {
                return checkbox.getAttribute('data-main-folder') === mainFolder;
            });
        }

        function applyFolderSelectionToProjects() {
            var selectedInEntity = selectedProjectsInCurrentEntity();
            var activeFolders = new Set();

            folderChecks.forEach(function (checkbox) {
                if (checkbox.checked) {
                    activeFolders.add(checkbox.value);
                }
            });

            selectedInEntity.forEach(function (projectId) {
                foldersByProject[projectId] = new Set(activeFolders);
            });

            syncHiddenInputs();
            renderSummary();
        }

        function updateMainFolderCheckStates() {
            mainFolderChecks.forEach(function (mainCheckbox) {
                var mainFolder = mainCheckbox.getAttribute('data-main-folder');
                var subs = subfolderChecksForMain(mainFolder);
                if (!subs.length) {
                    mainCheckbox.checked = false;
                    mainCheckbox.indeterminate = false;
                    return;
                }

                var checkedCount = subs.filter(function (checkbox) { return checkbox.checked; }).length;
                mainCheckbox.checked = checkedCount === subs.length;
                mainCheckbox.indeterminate = checkedCount > 0 && checkedCount < subs.length;
            });
        }

        function renderProjects() {
            if (!projectSelect) return;

            var projects = projectsForCurrentEntity();
            var selectedInEntity = selectedProjectsInCurrentEntity();
            projectSelect.innerHTML = '';

            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '— Select project —';
            projectSelect.appendChild(placeholder);

            if (!projects.length) {
                projectsEmpty.style.display = 'block';
                projectSelect.style.display = 'none';
                foldersPanel.style.display = 'none';
                return;
            }

            projectsEmpty.style.display = 'none';
            projectSelect.style.display = '';

            projects.forEach(function (project) {
                var projectId = String(project.id);
                var option = document.createElement('option');
                option.value = projectId;
                option.textContent = project.label;
                if (selectedInEntity.includes(projectId)) {
                    option.selected = true;
                }
                projectSelect.appendChild(option);
            });

            if (selectedInEntity.length === 1) {
                projectSelect.value = selectedInEntity[0];
            } else if (selectedInEntity.length > 1) {
                projectSelect.value = selectedInEntity[0];
            } else {
                projectSelect.value = '';
            }

            renderFolders();
        }

        function setProjectSelectionForCurrentEntity(projectId) {
            projectsForCurrentEntity().forEach(function (project) {
                var id = String(project.id);
                selectedProjects.delete(id);
                delete foldersByProject[id];
            });

            if (projectId) {
                selectedProjects.add(projectId);
                if (!foldersByProject[projectId]) {
                    foldersByProject[projectId] = new Set();
                }
            }
        }

        function renderFolders() {
            var selectedInEntity = selectedProjectsInCurrentEntity();
            if (!selectedInEntity.length) {
                foldersPanel.style.display = 'none';
                return;
            }

            foldersPanel.style.display = 'block';
            var activeFolders = folderUnionForProjects(selectedInEntity);

            folderChecks.forEach(function (checkbox) {
                checkbox.checked = activeFolders.has(checkbox.value);
            });
            updateMainFolderCheckStates();
        }

        if (projectSelect) {
            projectSelect.addEventListener('change', function () {
                setProjectSelectionForCurrentEntity(projectSelect.value);
                renderFolders();
                syncHiddenInputs();
                renderSummary();
            });
        }

        if (entitySelect) {
            entitySelect.addEventListener('change', function () {
                renderProjects();
            });
        }

        mainFolderChecks.forEach(function (mainCheckbox) {
            mainCheckbox.addEventListener('change', function () {
                var mainFolder = mainCheckbox.getAttribute('data-main-folder');
                subfolderChecksForMain(mainFolder).forEach(function (checkbox) {
                    checkbox.checked = mainCheckbox.checked;
                });
                mainCheckbox.indeterminate = false;
                applyFolderSelectionToProjects();
            });
        });

        folderChecks.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                updateMainFolderCheckStates();
                applyFolderSelectionToProjects();
            });
        });

        renderProjects();
        syncHiddenInputs();
        renderSummary();
    });
</script>
@endif
