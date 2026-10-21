@php
    $bulkAccess = app(\App\Services\DocumentAccessService::class);
    $bulkFolderTrees = [];
    foreach ($entities ?? [] as $bulkEntity) {
        $bulkFolderTrees[$bulkEntity->id] = $bulkAccess->accessibleFolderTreeForEntity(auth()->user(), (int) $bulkEntity->id);
    }
    $bulkProjects = collect($projects ?? [])->map(fn ($p) => [
        'id' => $p->id,
        'entity_id' => $p->entity_id,
        'label' => $p->project_name.' ('.$p->project_number.')',
    ])->values();
@endphp

<style>
    .bulk-col {
        width: 36px;
        text-align: center;
    }

    .bulk-col input {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .bulk-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        padding: 8px 12px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }

    .bulk-toolbar button {
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0b1f3a;
        font: inherit;
        cursor: pointer;
    }

    .bulk-toolbar button:hover {
        background: #e2e8f0;
    }

    .bulk-toolbar-count {
        font-weight: 600;
        color: #0b1f3a;
        margin-right: 4px;
    }

    .bulk-toolbar .bulk-toolbar-delete {
        color: #b91c1c;
        border-color: #fecaca;
    }

    .bulk-toolbar .bulk-toolbar-clear {
        margin-left: auto;
        color: #475569;
    }

    tr.bulk-row-selected > td {
        background: #eef2ff;
    }

    .bulk-target-modal select {
        width: 100%;
        padding: 8px 12px;
    }
</style>

@if($errors->has('bulk'))
    <div class="error" style="color: #b91c1c; margin-bottom: 10px;">{{ $errors->first('bulk') }}</div>
@endif

<form id="bulk-form" method="POST" action="" style="display: none;">
    @csrf
    <div data-bulk-ids></div>
    <input type="hidden" name="target_entity_id">
    <input type="hidden" name="target_project_id">
    <input type="hidden" name="target_main_folder">
    <input type="hidden" name="target_document_type">
</form>

<div id="bulk-target-modal" class="share-modal bulk-target-modal" aria-hidden="true">
    <div class="share-modal-backdrop" onclick="closeBulkTargetModal()"></div>
    <div class="share-modal-card" role="dialog" aria-labelledby="bulk-target-title" aria-modal="true">
        <h3 id="bulk-target-title">Move to</h3>
        <p class="share-modal-file" id="bulk-target-summary"></p>
        <div id="bulk-target-error" class="share-modal-error" style="display: none;"></div>

        <div class="share-modal-field">
            <label for="bulk-target-entity">Entity</label>
            <select id="bulk-target-entity">
                <option value="">Select entity</option>
                @foreach($entities ?? [] as $e)
                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="share-modal-field">
            <label for="bulk-target-project">Project</label>
            <select id="bulk-target-project">
                <option value="">Select project</option>
            </select>
        </div>
        <div class="share-modal-field">
            <label for="bulk-target-main">Category</label>
            <select id="bulk-target-main">
                <option value="">Select category</option>
            </select>
        </div>
        <div class="share-modal-field">
            <label for="bulk-target-type">Folder</label>
            <select id="bulk-target-type">
                <option value="">Select folder</option>
            </select>
        </div>

        <div class="share-modal-actions">
            <button type="button" class="share-modal-cancel" onclick="closeBulkTargetModal()">Cancel</button>
            <button type="button" class="share-modal-send" id="bulk-target-submit" onclick="submitBulkTarget()">Move</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var bulkRoutes = {
            download: @json(route('documents.bulk.download')),
            move: @json(route('documents.bulk.move')),
            copy: @json(route('documents.bulk.copy')),
            'delete': @json(route('documents.bulk.delete'))
        };
        var bulkProjects = @json($bulkProjects);
        var bulkFolderTrees = @json($bulkFolderTrees);
        var defaultEntityId = @json((string) ($currentEntityId ?? request('entity_id') ?? ''));
        var defaultProjectId = @json((string) request('project_id', ''));
        var bulkMode = 'move';

        function rowBoxes() {
            return Array.from(document.querySelectorAll('input.bulk-select'));
        }

        function selectedIds() {
            return rowBoxes().filter(function (box) { return box.checked; }).map(function (box) { return box.value; });
        }

        function refreshBulkUi() {
            var ids = selectedIds();
            var boxes = rowBoxes();
            document.querySelectorAll('[data-bulk-toolbar]').forEach(function (bar) {
                bar.style.display = ids.length > 0 ? 'flex' : 'none';
            });
            document.querySelectorAll('[data-bulk-count]').forEach(function (el) {
                el.textContent = ids.length;
            });
            boxes.forEach(function (box) {
                var row = box.closest('tr');
                if (row) row.classList.toggle('bulk-row-selected', box.checked);
            });
            document.querySelectorAll('input.bulk-select-all').forEach(function (all) {
                all.checked = boxes.length > 0 && ids.length === boxes.length;
                all.indeterminate = ids.length > 0 && ids.length < boxes.length;
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target.matches('input.bulk-select-all')) {
                rowBoxes().forEach(function (box) { box.checked = e.target.checked; });
                refreshBulkUi();
            } else if (e.target.matches('input.bulk-select')) {
                refreshBulkUi();
            }
        });

        window.bulkClearSelection = function () {
            rowBoxes().forEach(function (box) { box.checked = false; });
            refreshBulkUi();
        };

        window.bulkSubmit = function (action) {
            var ids = selectedIds();
            if (ids.length === 0) return;
            if (action === 'delete' && !confirm('Move ' + ids.length + ' selected file(s) to Trash? You can restore them later.')) {
                return;
            }
            var form = document.getElementById('bulk-form');
            var holder = form.querySelector('[data-bulk-ids]');
            holder.innerHTML = '';
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                holder.appendChild(input);
            });
            form.action = bulkRoutes[action];
            form.submit();
        };

        var entitySelect = document.getElementById('bulk-target-entity');
        var projectSelect = document.getElementById('bulk-target-project');
        var mainSelect = document.getElementById('bulk-target-main');
        var typeSelect = document.getElementById('bulk-target-type');

        function fillSelect(select, placeholder, items) {
            select.innerHTML = '';
            var first = document.createElement('option');
            first.value = '';
            first.textContent = placeholder;
            select.appendChild(first);
            items.forEach(function (item) {
                var option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.label;
                select.appendChild(option);
            });
        }

        function fillProjects() {
            var entityId = entitySelect.value;
            fillSelect(projectSelect, 'Select project', bulkProjects
                .filter(function (p) { return entityId !== '' && String(p.entity_id) === entityId; })
                .map(function (p) { return { value: String(p.id), label: p.label }; }));
        }

        function fillMainFolders() {
            var tree = bulkFolderTrees[entitySelect.value] || {};
            fillSelect(mainSelect, 'Select category', Object.keys(tree).map(function (name) {
                return { value: name, label: name };
            }));
            fillTypes();
        }

        function fillTypes() {
            var tree = bulkFolderTrees[entitySelect.value] || {};
            var subs = tree[mainSelect.value] || [];
            fillSelect(typeSelect, 'Select folder', subs.map(function (name) {
                return { value: name, label: name };
            }));
        }

        entitySelect.addEventListener('change', function () {
            fillProjects();
            fillMainFolders();
        });
        mainSelect.addEventListener('change', fillTypes);

        window.openBulkTargetModal = function (mode) {
            var ids = selectedIds();
            if (ids.length === 0) return;
            bulkMode = mode;
            var verb = mode === 'copy' ? 'Copy' : 'Move';
            document.getElementById('bulk-target-title').textContent = verb + ' to';
            document.getElementById('bulk-target-submit').textContent = verb;
            document.getElementById('bulk-target-summary').textContent = ids.length + ' file(s) selected';
            document.getElementById('bulk-target-error').style.display = 'none';

            if (entitySelect.value === '' && defaultEntityId !== '') {
                entitySelect.value = defaultEntityId;
                fillProjects();
                fillMainFolders();
                if (defaultProjectId !== '') projectSelect.value = defaultProjectId;
            }

            var modal = document.getElementById('bulk-target-modal');
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        };

        window.closeBulkTargetModal = function () {
            var modal = document.getElementById('bulk-target-modal');
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        };

        window.submitBulkTarget = function () {
            var error = document.getElementById('bulk-target-error');
            if (!entitySelect.value || !projectSelect.value || !mainSelect.value || !typeSelect.value) {
                error.textContent = 'Select entity, project, category and folder.';
                error.style.display = 'block';
                return;
            }
            var form = document.getElementById('bulk-form');
            form.querySelector('[name="target_entity_id"]').value = entitySelect.value;
            form.querySelector('[name="target_project_id"]').value = projectSelect.value;
            form.querySelector('[name="target_main_folder"]').value = mainSelect.value;
            form.querySelector('[name="target_document_type"]').value = typeSelect.value;
            document.getElementById('bulk-target-submit').disabled = true;
            window.bulkSubmit(bulkMode);
        };

        refreshBulkUi();
    })();
</script>
