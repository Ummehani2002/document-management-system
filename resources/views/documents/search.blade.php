@extends('layouts.app')

@section('content')

<h2>Search Documents</h2>

<style>
    .doc-actions-menu {
        position: relative;
        display: inline-block;
    }

    .doc-actions-trigger {
        background: transparent;
        border: 1px solid #cbd5e1;
        color: #334155;
        width: 32px;
        height: 32px;
        padding: 0;
        border-radius: 6px;
        font-size: 1.1rem;
        line-height: 1;
        cursor: pointer;
    }

    .doc-actions-trigger:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
    }

    .doc-actions-dropdown {
        position: absolute;
        right: 0;
        top: calc(100% + 4px);
        min-width: 140px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        z-index: 30;
        overflow: hidden;
    }

    .doc-actions-dropdown button,
    .doc-actions-dropdown a,
    .doc-actions-dropdown .doc-actions-delete-btn {
        display: block;
        width: 100%;
        text-align: left;
        padding: 10px 14px;
        border: none;
        background: #fff;
        color: #1e293b;
        font-size: 0.85rem;
        text-decoration: none;
        cursor: pointer;
        box-sizing: border-box;
    }

    .doc-actions-dropdown button:hover,
    .doc-actions-dropdown a:hover,
    .doc-actions-dropdown .doc-actions-delete-btn:hover {
        background: #f8fafc;
    }

    .doc-actions-dropdown .doc-actions-delete-btn {
        color: #b91c1c;
    }

    .doc-actions-dropdown form {
        margin: 0;
    }

    .preview-loading {
        margin: 0 0 10px;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #475569;
        font-size: 0.85rem;
    }

    .preview-frame {
        width: 100%;
        height: 75vh;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
    }

    .share-modal {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .share-modal.is-open {
        display: flex;
    }

    .share-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
    }

    .share-modal-card {
        position: relative;
        width: 100%;
        max-width: 480px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 24px 60px -24px rgba(15, 23, 42, 0.45);
        padding: 24px 24px 20px;
        animation: shareModalIn 0.2s ease-out;
    }

    @keyframes shareModalIn {
        from { opacity: 0; transform: translateY(12px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .share-modal-card h3 {
        margin: 0 0 6px;
        font-size: 1.15rem;
        color: #212d3e;
    }

    .share-modal-file {
        margin: 0 0 18px;
        color: #64748b;
        font-size: 0.88rem;
        word-break: break-word;
    }

    .share-modal-field {
        margin-bottom: 14px;
    }

    .share-email-wrap {
        position: relative;
    }

    .share-email-suggestions {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        z-index: 20;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
        max-height: 320px;
        overflow-y: auto;
        display: none;
        padding: 4px 0;
    }

    .share-email-suggestions.is-open {
        display: block;
    }

    .share-email-suggestions-section {
        padding: 6px 12px 4px;
        color: #0f6cbd;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.3;
    }

    .share-email-suggestion {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 6px 12px;
        border: 1px solid transparent;
        background: #fff;
        text-align: left;
        cursor: pointer;
        font: inherit;
        box-sizing: border-box;
    }

    .share-email-suggestion:hover,
    .share-email-suggestion.is-active {
        background: #f3f2f1;
        border-color: #c8c6c4;
    }

    .share-email-suggestion-avatar {
        flex: 0 0 32px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .share-email-suggestion-body {
        min-width: 0;
        flex: 1;
    }

    .share-email-suggestion-name {
        display: block;
        color: #1e293b;
        font-size: 0.88rem;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .share-email-suggestion-email {
        display: block;
        color: #64748b;
        font-size: 0.78rem;
        line-height: 1.25;
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .share-email-suggestions-empty {
        padding: 10px 12px;
        color: #64748b;
        font-size: 0.82rem;
    }

    .share-modal-field label {
        display: block;
        margin-bottom: 6px;
        color: #334155;
        font-size: 0.85rem;
    }

    .share-modal-field input,
    .share-modal-field textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font: inherit;
        box-sizing: border-box;
    }

    .share-modal-field textarea {
        resize: vertical;
        min-height: 84px;
    }

    .share-modal-from {
        margin: 0 0 18px;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #475569;
        font-size: 0.82rem;
    }

    .share-modal-error {
        margin: 0 0 14px;
        padding: 10px 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        border-radius: 8px;
        font-size: 0.84rem;
    }

    .share-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .share-modal-actions button {
        padding: 10px 16px;
        border-radius: 8px;
        font: inherit;
        cursor: pointer;
    }

    .share-modal-cancel {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #334155;
    }

    .share-modal-send {
        background: #212d3e;
        border: 1px solid #212d3e;
        color: #fff;
    }

    .share-modal-send:disabled {
        opacity: 0.6;
        cursor: wait;
    }
</style>
@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif
@if(empty($fromSidebar) || empty(request('project_id')) || empty(request('document_type')))

@endif
@if(!empty($needsProjectSelection))
    <form method="GET" action="{{ route('documents.search') }}" class="search-form">
        <input type="hidden" name="from_sidebar" value="1">
        <input type="hidden" name="main_folder" value="{{ request('main_folder') }}">
        <input type="hidden" name="document_type" value="{{ request('document_type') }}">
        <div class="card" style="max-width: 520px;">
            <label for="entity_id" style="display: block; margin-bottom: 4px; font-weight: 500;">Entity </label>
            <select name="entity_id" id="entity_id" required style="width: 100%; padding: 8px 12px; margin-bottom: 12px;">
                <option value="">Select entity</option>
                @foreach($entities ?? [] as $e)
                    <option value="{{ $e->id }}" {{ (int) request('entity_id') === $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                @endforeach
            </select>

            <label for="project_id" style="display: block; margin-bottom: 4px; font-weight: 500;">Project</label>
            <select name="project_id" id="project_id" required style="width: 100%; padding: 8px 12px;">
                <option value="">Select project</option>
                @foreach($projects ?? [] as $p)
                    <option
                        value="{{ $p->id }}"
                        data-entity="{{ $p->entity_id }}"
                        {{ (int) request('project_id') === $p->id ? 'selected' : '' }}
                    >
                        {{ $p->project_name }} ({{ $p->project_number }})
                    </option>
                @endforeach
            </select>
            <button type="submit" style="margin-top: 12px;">View Files</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var entitySelect = document.getElementById('entity_id');
            var projectSelect = document.getElementById('project_id');
            if (!entitySelect || !projectSelect) return;

            var selectedProjectId = projectSelect.value;
            var allProjects = Array.from(projectSelect.querySelectorAll('option[data-entity]')).map(function (option) {
                return {
                    value: option.value,
                    entityId: option.getAttribute('data-entity'),
                    label: option.textContent
                };
            });

            function projectMatchesEntity(selectedEntityId, projectEntityAttr) {
                if (!selectedEntityId) {
                    return true;
                }
                return String(selectedEntityId) === String(projectEntityAttr || '');
            }

            function filterProjects() {
                var entityId = entitySelect.value;
                projectSelect.innerHTML = '<option value="">Select project</option>';
                allProjects.forEach(function (project) {
                    if (!projectMatchesEntity(entityId, project.entityId)) return;
                    var option = document.createElement('option');
                    option.value = project.value;
                    option.textContent = project.label;
                    if (project.value === selectedProjectId) {
                        option.selected = true;
                    }
                    projectSelect.appendChild(option);
                });
            }

            entitySelect.addEventListener('change', function () {
                selectedProjectId = '';
                filterProjects();
            });

            filterProjects();
        });
    </script>
@else
    @if(!empty($fromSidebar) && request('project_id') && request('document_type'))
    @else
        <form method="GET" action="{{ route('documents.search') }}" class="search-form">
            @if(!empty($fromSidebar))
                <input type="hidden" name="from_sidebar" value="1">
            @endif
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 16px;">
                <div style="flex: 1 1 180px; min-width: 160px;">
                    <label for="keyword" style="display: block; margin-bottom: 4px; font-weight: 500;">Keyword</label>
                    <input type="text" name="keyword" id="keyword" placeholder="Search in text..." value="{{ old('keyword', $keyword ?? '') }}" style="width: 100%; padding: 8px 12px; box-sizing: border-box; margin: 0;">
                </div>
                <div style="flex: 1 1 150px; min-width: 140px;">
                    <label for="entity_id" style="display: block; margin-bottom: 4px; font-weight: 500;">Entity</label>
                    <select name="entity_id" id="entity_id" style="width: 100%; padding: 8px 12px; box-sizing: border-box; margin: 0;">
                        <option value="">All entities</option>
                        @foreach($entities ?? [] as $e)
                            <option value="{{ $e->id }}" {{ (int) request('entity_id') === $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1 1 180px; min-width: 160px;">
                    <label for="project_id" style="display: block; margin-bottom: 4px; font-weight: 500;">Project</label>
                    <select name="project_id" id="project_id" style="width: 100%; padding: 8px 12px; box-sizing: border-box; margin: 0;">
                        <option value="">All projects</option>
                        @foreach($projects ?? [] as $p)
                            <option
                                value="{{ $p->id }}"
                                data-entity="{{ $p->entity_id }}"
                                {{ (int) request('project_id') === $p->id ? 'selected' : '' }}
                            >
                                {{ $p->project_name }} ({{ $p->project_number }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1 1 150px; min-width: 140px;">
                    <label for="discipline" style="display: block; margin-bottom: 4px; font-weight: 500;">Discipline</label>
                    <select name="discipline" id="discipline" style="width: 100%; padding: 8px 12px; box-sizing: border-box; margin: 0;">
                        <option value="">All disciplines</option>
                        @foreach($disciplines ?? [] as $d)
                            <option value="{{ $d }}" {{ request('discipline') === $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1 1 150px; min-width: 140px;">
                    <label for="document_type" style="display: block; margin-bottom: 4px; font-weight: 500;">Doc type</label>
                    <select name="document_type" id="document_type" style="width: 100%; padding: 8px 12px; box-sizing: border-box; margin: 0;">
                        <option value="">All types</option>
                        @foreach($documentTypes ?? [] as $t)
                            <option value="{{ $t }}" {{ request('document_type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 0 0 100%;">
                    <button type="submit" style="margin: 4px 0 0; padding: 9px 22px;">Search</button>
                </div>
            </div>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var entitySelect = document.getElementById('entity_id');
                var projectSelect = document.getElementById('project_id');
                if (!entitySelect || !projectSelect) return;

                var allProjects = Array.from(projectSelect.querySelectorAll('option[data-entity]')).map(function (option) {
                    return {
                        value: option.value,
                        entityId: option.getAttribute('data-entity'),
                        label: option.textContent
                    };
                });

                function projectMatchesEntity(selectedEntityId, projectEntityAttr) {
                    if (!selectedEntityId) {
                        return true;
                    }
                    return String(selectedEntityId) === String(projectEntityAttr || '');
                }

                function filterProjectsByEntity() {
                    var entityId = entitySelect.value;
                    var previousProjectId = projectSelect.value;

                    projectSelect.innerHTML = '';
                    var allOpt = document.createElement('option');
                    allOpt.value = '';
                    allOpt.textContent = 'All projects';
                    projectSelect.appendChild(allOpt);

                    allProjects.forEach(function (project) {
                        if (!projectMatchesEntity(entityId, project.entityId)) {
                            return;
                        }
                        var option = document.createElement('option');
                        option.value = project.value;
                        option.textContent = project.label;
                        if (previousProjectId && project.value === previousProjectId) {
                            option.selected = true;
                        }
                        projectSelect.appendChild(option);
                    });

                    if (previousProjectId && projectSelect.value !== previousProjectId) {
                        projectSelect.value = '';
                    }
                }

                entitySelect.addEventListener('change', filterProjectsByEntity);
                filterProjectsByEntity();
            });
        </script>
    @endif
@endif

@if(isset($documents) && $documents !== null)
    @if(!empty($fromSidebar) && request('project_id') && request('document_type'))
        @php
            $folderTree = \App\Services\DocumentFilenameParser::sidebarFolderTree();
            $activeType = request('document_type');
            $mainFolder = request('main_folder');
            if (!$mainFolder && $activeType) {
                foreach ($folderTree as $folderName => $types) {
                    if (in_array($activeType, $types, true)) {
                        $mainFolder = $folderName;
                        break;
                    }
                }
            }
            $selectedProject = $projects->firstWhere('id', (int) request('project_id'));
        @endphp

        <div>
            <form method="GET" action="{{ route('documents.search') }}" class="card" style="margin-top: 0; margin-bottom: 12px; max-width: 720px;">
                <input type="hidden" name="from_sidebar" value="1">
                <input type="hidden" name="main_folder" value="{{ $mainFolder }}">
                <input type="hidden" name="document_type" value="{{ $activeType }}">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; align-items: end;">
                    <div>
                        <label for="entity_id_switch" style="display: block; margin-bottom: 4px; font-weight: 500;">Entity</label>
                        <select name="entity_id" id="entity_id_switch" style="width: 100%; padding: 8px 12px;">
                            <option value="">All entities</option>
                            @foreach($entities ?? [] as $e)
                                <option value="{{ $e->id }}" {{ (int) request('entity_id') === $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="project_id_switch" style="display: block; margin-bottom: 4px; font-weight: 500;">Project</label>
                        <select name="project_id" id="project_id_switch" required style="width: 100%; padding: 8px 12px;">
                            <option value="">Select project</option>
                            @foreach($projects ?? [] as $p)
                                <option
                                    value="{{ $p->id }}"
                                    data-entity="{{ $p->entity_id }}"
                                    {{ (int) request('project_id') === $p->id ? 'selected' : '' }}
                                >
                                    {{ $p->project_name }} ({{ $p->project_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit">Search</button>
                    </div>
                </div>
                <p style="margin: 10px 0 0; color: #64748b; font-size: 0.86rem;">
                    Document Type: <strong>{{ $activeType }}</strong>
                </p>
            </form>

            <section>
                <div class="card dms-grid-wrap" style="margin-top: 0;">
                    <table class="dms-grid-table min-w-lg">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Reference No</th>
                                <th>Subject</th>
                                <th>Project Discipline</th>
                                <th>Project Number</th>
                                <th>Project Name</th>
                                <th>Project Client</th>
                                <th>Project Consultant</th>
                                <th>Modified</th>
                                <th>Modified By</th>
                                <th class="text-center" style="width: 48px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $doc)
                                @php
                                    $meta = \App\Services\DocumentFilenameParser::extractReferenceAndSubject($doc->ocr_text, $doc->file_name);
                                    $referenceNo = $meta['reference_no'] ?? '—';
                                    $subject = $meta['subject'] ?? '—';
                                @endphp
                                <tr>
                                    <td>
                                        @if(!empty($doc->file_available))
                                            <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank">{{ $doc->file_name }}</a>
                                        @else
                                            <span>{{ $doc->file_name }}</span>
                                            <div style="color:#b91c1c; font-size:0.8rem; margin-top:4px;">File unavailable in storage</div>
                                        @endif
                                    </td>
                                    <td>{{ $referenceNo }}</td>
                                    <td>{{ $subject }}</td>
                                    <td>{{ $doc->discipline ?: '—' }}</td>
                                    <td>{{ $doc->project?->project_number ?? '—' }}</td>
                                    <td>{{ $doc->project?->project_name ?? '—' }}</td>
                                    <td>{{ $doc->project?->client_name ?? '—' }}</td>
                                    <td>{{ $doc->project?->consultant ?? '—' }}</td>
                                    <td>{{ format_model_datetime($doc, 'updated_at') }}</td>
                                    <td>{{ $doc->modifiedBy?->username ?? '—' }}</td>
                                    <td class="text-center">
                                        <div class="doc-actions-menu">
                                            <button
                                                type="button"
                                                class="doc-actions-trigger"
                                                onclick="toggleDocActionsMenu(event, '{{ $doc->id }}')"
                                                aria-label="Document actions"
                                            >&#8942;</button>
                                            <div id="doc-actions-dropdown-{{ $doc->id }}" class="doc-actions-dropdown" style="display: none;" onclick="event.stopPropagation();">
                                                @if(!empty($doc->file_available))
                                                    <button type="button" onclick="closeDocActionsMenu('{{ $doc->id }}'); toggleInlinePreview('{{ $doc->id }}')">View</button>
                                                    <a href="{{ route('documents.edit', ['id' => $doc->id, 'return_url' => request()->fullUrl()]) }}">Edit</a>
                                                    <button type="button" class="doc-share-btn" data-share-id="{{ $doc->id }}" data-share-file="{{ $doc->file_name }}" data-share-project="{{ $doc->project?->project_number ?? '' }}">Share</button>
                                                    <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" onsubmit="return confirm('Delete this file?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="doc-actions-delete-btn">Delete</button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" onsubmit="return confirm('Delete this record? File is already missing from storage.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="doc-actions-delete-btn">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @if(!empty($doc->file_available))
                                    <tr id="preview-row-{{ $doc->id }}" style="display:none; border-bottom: 1px solid #e2e8f0;">
                                        <td colspan="11" style="padding: 10px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                                <strong>Inline Preview</strong>
                                                <button type="button" style="padding:4px 10px;" onclick="closeInlinePreview('{{ $doc->id }}')">Close preview</button>
                                            </div>
                                            <p id="preview-loading-{{ $doc->id }}" class="preview-loading" style="display:none;">Loading preview…</p>
                                            <iframe
                                                id="preview-frame-{{ $doc->id }}"
                                                class="preview-frame"
                                                src="about:blank"
                                                title="Preview {{ $doc->file_name }}"
                                                style="display:none;"
                                            ></iframe>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="11" style="padding: 14px;">No files found in this folder for selected project.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $documents->links() }}
            </section>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var entitySelect = document.getElementById('entity_id_switch');
                var projectSelect = document.getElementById('project_id_switch');
                if (!entitySelect || !projectSelect) return;

                var selectedProjectId = projectSelect.value;
                var allProjects = Array.from(projectSelect.querySelectorAll('option[data-entity]')).map(function (option) {
                    return {
                        value: option.value,
                        entityId: option.getAttribute('data-entity'),
                        label: option.textContent
                    };
                });

                function projectMatchesEntity(selectedEntityId, projectEntityAttr) {
                    if (!selectedEntityId) {
                        return true;
                    }
                    return String(selectedEntityId) === String(projectEntityAttr || '');
                }

                function filterProjects() {
                    var entityId = entitySelect.value;
                    projectSelect.innerHTML = '<option value="">Select project</option>';
                    allProjects.forEach(function (project) {
                        if (!projectMatchesEntity(entityId, project.entityId)) return;
                        var option = document.createElement('option');
                        option.value = project.value;
                        option.textContent = project.label;
                        if (project.value === selectedProjectId) {
                            option.selected = true;
                        }
                        projectSelect.appendChild(option);
                    });
                }

                entitySelect.addEventListener('change', function () {
                    selectedProjectId = '';
                    filterProjects();
                });

                filterProjects();
            });
        </script>
    @else
        <div class="card dms-grid-wrap" style="margin-top: 0;">
            <table class="dms-grid-table min-w-lg">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Reference No</th>
                        <th>Subject</th>
                        <th>Folder</th>
                        <th>Project</th>
                        <th>Modified</th>
                        <th>Modified By</th>
                        <th style="text-align: center; width: 48px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        @php
                            $meta = \App\Services\DocumentFilenameParser::extractReferenceAndSubject($doc->ocr_text, $doc->file_name);
                            $referenceNo = $meta['reference_no'] ?? '—';
                            $subject = $meta['subject'] ?? '—';
                        @endphp
                        <tr>
                            <td>
                                @if(!empty($doc->file_available))
                                    <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank">{{ $doc->file_name }}</a>
                                @else
                                    <span>{{ $doc->file_name }}</span>
                                    <div style="color:#b91c1c; font-size:0.8rem; margin-top:4px;">File unavailable in storage</div>
                                @endif
                            </td>
                            <td>{{ $referenceNo }}</td>
                            <td>{{ $subject }}</td>
                            <td>{{ $doc->display_folder }}</td>
                            <td>{{ $doc->project?->project_number ?? '—' }}@if($doc->project) — {{ $doc->project->project_name }}@endif</td>
                            <td>{{ format_model_datetime($doc, 'updated_at') }}</td>
                            <td>{{ $doc->modifiedBy?->username ?? '—' }}</td>
                            <td style="text-align: center;">
                                <div class="doc-actions-menu">
                                    <button
                                        type="button"
                                        class="doc-actions-trigger"
                                        onclick="toggleDocActionsMenu(event, '{{ $doc->id }}')"
                                        aria-label="Document actions"
                                    >&#8942;</button>
                                    <div id="doc-actions-dropdown-{{ $doc->id }}" class="doc-actions-dropdown" style="display: none;" onclick="event.stopPropagation();">
                                        @if(!empty($doc->file_available))
                                            <a href="{{ route('documents.download', ['id' => $doc->id]) }}">Download</a>
                                            <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank" rel="noopener">Open in new tab</a>
                                            <button type="button" onclick="closeDocActionsMenu('{{ $doc->id }}'); toggleInlinePreview('{{ $doc->id }}')">View</button>
                                            <a href="{{ route('documents.edit', ['id' => $doc->id, 'return_url' => request()->fullUrl()]) }}">Edit</a>
                                            <button type="button" class="doc-share-btn" data-share-id="{{ $doc->id }}" data-share-file="{{ $doc->file_name }}" data-share-project="{{ $doc->project?->project_number ?? '' }}">Share</button>
                                            <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" onsubmit="return confirm('Delete this file?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="doc-actions-delete-btn">Delete</button>
                                            </form>
                                        @else
                                            <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" onsubmit="return confirm('Delete this record? File is already missing.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="doc-actions-delete-btn">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @if(!empty($doc->file_available))
                            <tr id="preview-row-{{ $doc->id }}" style="display:none;">
                                <td colspan="8">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <strong>Inline Preview</strong>
                                        <button type="button" style="padding:4px 10px;" onclick="closeInlinePreview('{{ $doc->id }}')">Close preview</button>
                                    </div>
                                    <p id="preview-loading-{{ $doc->id }}" class="preview-loading" style="display:none;">Loading preview…</p>
                                    <iframe
                                        id="preview-frame-{{ $doc->id }}"
                                        class="preview-frame"
                                        src="about:blank"
                                        title="Preview {{ $doc->file_name }}"
                                        style="display:none;"
                                    ></iframe>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8">No documents found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $documents->links() }}
    @endif
@endif

@php
    $shareErrorDocId = null;
    $shareErrorMessage = null;
    $shareContext = session('share_context', []);
    foreach ($errors->keys() as $errorKey) {
        if (str_starts_with($errorKey, 'share_email_')) {
            $shareErrorDocId = (int) str_replace('share_email_', '', $errorKey);
            $shareErrorMessage = $errors->first($errorKey);
            break;
        }
    }
@endphp

<div id="share-modal" class="share-modal" aria-hidden="true">
    <div class="share-modal-backdrop" onclick="closeShareModal()"></div>
    <div class="share-modal-card" role="dialog" aria-labelledby="share-modal-title" aria-modal="true">
        <h3 id="share-modal-title">Share document</h3>
        <p class="share-modal-file" id="share-modal-file-name"></p>

        <div id="share-modal-error" class="share-modal-error" style="display:none;"></div>

        <form id="share-modal-form" method="POST" action="">
            @csrf
            <div class="share-modal-field">
                <label for="share-modal-email">Recipient email address</label>
                <div class="share-email-wrap">
                    <input
                        type="text"
                        id="share-modal-email"
                        name="email"
                        placeholder="Start typing a name or email..."
                        value="{{ old('email') }}"
                        required
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="share-email-suggestions"
                    >
                    <div id="share-email-suggestions" class="share-email-suggestions" role="listbox"></div>
                </div>
            </div>
            <div class="share-modal-field">
                <label for="share-modal-message">Message <span style="color:#94a3b8;">(optional)</span></label>
                <textarea
                    id="share-modal-message"
                    name="message"
                    placeholder="Add a short note for the recipient..."
                    maxlength="500"
                >{{ old('message') }}</textarea>
            </div>
            <p class="share-modal-from">
                This email will be sent from <strong>{{ auth()->user()?->email }}</strong>
                with the document attached.
            </p>
            <div class="share-modal-actions">
                <button type="button" class="share-modal-cancel" onclick="closeShareModal()">Cancel</button>
                <button type="submit" class="share-modal-send" id="share-modal-send-btn">Send email</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleDocActionsMenu(event, documentId) {
        event.stopPropagation();
        var dropdown = document.getElementById('doc-actions-dropdown-' + documentId);
        if (!dropdown) return;

        var opening = dropdown.style.display === 'none' || dropdown.style.display === '';
        document.querySelectorAll('.doc-actions-dropdown').forEach(function (menu) {
            menu.style.display = 'none';
        });
        dropdown.style.display = opening ? 'block' : 'none';
    }

    function closeDocActionsMenu(documentId) {
        var dropdown = document.getElementById('doc-actions-dropdown-' + documentId);
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    document.addEventListener('click', function () {
        document.querySelectorAll('.doc-actions-dropdown').forEach(function (menu) {
            menu.style.display = 'none';
        });
    });

    var previewCache = {};

    function previewUrlEndpoint(documentId) {
        return @json(url('/documents')) + '/' + documentId + '/preview-url';
    }

    function showPreviewLoading(documentId, message) {
        var loading = document.getElementById('preview-loading-' + documentId);
        if (!loading) return;
        loading.textContent = message || 'Loading preview…';
        loading.style.display = 'block';
    }

    function hidePreviewLoading(documentId) {
        var loading = document.getElementById('preview-loading-' + documentId);
        if (loading) {
            loading.style.display = 'none';
        }
    }

    function showPreviewFrame(documentId) {
        var frame = document.getElementById('preview-frame-' + documentId);
        if (frame) {
            frame.style.display = 'block';
        }
        hidePreviewLoading(documentId);
    }

    function toggleInlinePreview(documentId) {
        var previewRow = document.getElementById('preview-row-' + documentId);
        var frame = document.getElementById('preview-frame-' + documentId);
        if (!previewRow || !frame) return;

        var opening = previewRow.style.display === 'none' || previewRow.style.display === '';

        document.querySelectorAll('[id^="preview-row-"]').forEach(function (row) {
            if (row.id !== 'preview-row-' + documentId) {
                row.style.display = 'none';
            }
        });

        if (!opening) {
            previewRow.style.display = 'none';
            return;
        }

        previewRow.style.display = previewRow.tagName.toLowerCase() === 'tr' ? 'table-row' : 'block';

        if (previewCache[documentId]) {
            frame.src = previewCache[documentId];
            showPreviewFrame(documentId);
            return;
        }

        showPreviewLoading(documentId);
        frame.style.display = 'none';
        frame.src = 'about:blank';

        fetch(previewUrlEndpoint(documentId), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Preview unavailable');
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.url) {
                    throw new Error('Preview unavailable');
                }

                previewCache[documentId] = data.url;

                var revealFrame = function () {
                    showPreviewFrame(documentId);
                };

                var revealTimer = window.setTimeout(revealFrame, 700);
                frame.onload = function () {
                    window.clearTimeout(revealTimer);
                    revealFrame();
                };
                frame.onerror = function () {
                    window.clearTimeout(revealTimer);
                    delete previewCache[documentId];
                    showPreviewLoading(documentId, 'Preview could not load. Try opening in a new tab.');
                    frame.style.display = 'none';
                };

                frame.src = data.url;
            })
            .catch(function () {
                showPreviewLoading(documentId, 'Preview could not load. Try opening in a new tab.');
                frame.style.display = 'none';
            });
    }

    function closeInlinePreview(documentId) {
        var previewRow = document.getElementById('preview-row-' + documentId);
        if (previewRow) {
            previewRow.style.display = 'none';
        }
    }

    function openShareModal(documentId, fileName, projectNumber) {
        var modal = document.getElementById('share-modal');
        var form = document.getElementById('share-modal-form');
        var fileEl = document.getElementById('share-modal-file-name');
        var errorEl = document.getElementById('share-modal-error');
        var emailInput = document.getElementById('share-modal-email');
        var sendBtn = document.getElementById('share-modal-send-btn');
        if (!modal || !form || !fileEl) return;

        form.action = @json(url('/documents')) + '/' + documentId + '/share';
        fileEl.textContent = fileName + (projectNumber ? ' · Project ' + projectNumber : '');
        if (errorEl) {
            errorEl.style.display = 'none';
            errorEl.textContent = '';
        }
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send email';
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (emailInput) {
            emailInput.value = emailInput.value || '';
            setTimeout(function () { emailInput.focus(); }, 50);
        }
        hideShareEmailSuggestions();
    }

    function closeShareModal() {
        var modal = document.getElementById('share-modal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        hideShareEmailSuggestions();
    }

    var shareEmailSuggestTimer = null;
    var shareEmailActiveIndex = -1;
    var shareEmailSuggestionsData = [];
    var shareEmailSuggestionsUrl = @json(route('documents.share.email-suggestions'));

    function hideShareEmailSuggestions() {
        var list = document.getElementById('share-email-suggestions');
        var input = document.getElementById('share-modal-email');
        if (list) {
            list.classList.remove('is-open');
            list.innerHTML = '';
        }
        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }
        shareEmailActiveIndex = -1;
        shareEmailSuggestionsData = [];
    }

    function shareEmailInitials(name, email) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        }
        if (parts.length === 1 && parts[0].length >= 2) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        var local = String(email || '').split('@')[0] || '';
        return (local.slice(0, 2) || '?').toUpperCase();
    }

    function shareEmailAvatarColor(email) {
        var palette = ['#0f6cbd', '#8764b8', '#13a10e', '#c239b3', '#ca5010', '#038387', '#4f6bed', '#6b4c9a'];
        var sum = 0;
        for (var i = 0; i < email.length; i++) {
            sum += email.charCodeAt(i);
        }
        return palette[sum % palette.length];
    }

    function renderShareEmailSuggestions(payload) {
        var list = document.getElementById('share-email-suggestions');
        var input = document.getElementById('share-modal-email');
        if (!list || !input) return;

        var items = Array.isArray(payload) ? payload : (payload.suggestions || []);
        var hint = payload && payload.hint ? payload.hint : null;

        shareEmailSuggestionsData = items;
        shareEmailActiveIndex = -1;
        list.innerHTML = '';

        if (!items.length) {
            var empty = document.createElement('div');
            empty.className = 'share-email-suggestions-empty';
            empty.textContent = hint || 'No company contacts found. Keep typing the email address.';
            list.appendChild(empty);
            list.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
            return;
        }

        var recent = items.filter(function (item) { return item.group === 'recent'; });
        var other = items.filter(function (item) { return item.group !== 'recent'; });
        var flatIndex = 0;

        function appendSection(title, sectionItems) {
            if (!sectionItems.length) return;

            var heading = document.createElement('div');
            heading.className = 'share-email-suggestions-section';
            heading.textContent = title;
            list.appendChild(heading);

            sectionItems.forEach(function (item) {
                var currentIndex = flatIndex;
                flatIndex += 1;

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'share-email-suggestion';
                button.setAttribute('role', 'option');
                button.dataset.index = String(currentIndex);

                var avatar = document.createElement('span');
                avatar.className = 'share-email-suggestion-avatar';
                avatar.style.background = shareEmailAvatarColor(item.email);
                avatar.textContent = shareEmailInitials(item.name, item.email);

                var body = document.createElement('span');
                body.className = 'share-email-suggestion-body';

                var nameEl = document.createElement('span');
                nameEl.className = 'share-email-suggestion-name';
                nameEl.textContent = item.name || item.email;

                var emailEl = document.createElement('span');
                emailEl.className = 'share-email-suggestion-email';
                emailEl.textContent = item.email;

                body.appendChild(nameEl);
                body.appendChild(emailEl);
                button.appendChild(avatar);
                button.appendChild(body);

                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    selectShareEmailSuggestion(currentIndex);
                });

                list.appendChild(button);
            });
        }

        appendSection('Recent people', recent);
        appendSection('Other suggestions', other);

        list.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
    }

    function selectShareEmailSuggestion(index) {
        var item = shareEmailSuggestionsData[index];
        var input = document.getElementById('share-modal-email');
        if (!item || !input) return;
        input.value = item.email;
        hideShareEmailSuggestions();
        input.focus();
    }

    function setShareEmailActiveSuggestion(index) {
        var list = document.getElementById('share-email-suggestions');
        if (!list) return;

        var buttons = list.querySelectorAll('.share-email-suggestion');
        buttons.forEach(function (button, i) {
            button.classList.toggle('is-active', i === index);
        });
        shareEmailActiveIndex = index;
    }

    function fetchShareEmailSuggestions(query) {
        return fetch(shareEmailSuggestionsUrl + '?q=' + encodeURIComponent(query), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                return {
                    suggestions: Array.isArray(data.suggestions) ? data.suggestions : [],
                    hint: data.hint || null
                };
            })
            .catch(function () {
                return { suggestions: [], hint: null };
            });
    }

    function requestShareEmailSuggestions(query) {
        window.clearTimeout(shareEmailSuggestTimer);
        shareEmailSuggestTimer = window.setTimeout(function () {
            fetchShareEmailSuggestions(query).then(renderShareEmailSuggestions);
        }, 200);
    }

    function initShareEmailAutocomplete() {
        var input = document.getElementById('share-modal-email');
        if (!input || input.dataset.autocompleteReady === '1') return;
        input.dataset.autocompleteReady = '1';

        input.addEventListener('input', function () {
            var query = input.value.trim();

            if (query.length < 1) {
                hideShareEmailSuggestions();
                return;
            }

            requestShareEmailSuggestions(query);
        });

        input.addEventListener('focus', function () {
            var query = input.value.trim();
            if (query.length >= 1) {
                requestShareEmailSuggestions(query);
            }
        });

        input.addEventListener('keydown', function (event) {
            var list = document.getElementById('share-email-suggestions');
            if (!list || !list.classList.contains('is-open')) return;

            var count = shareEmailSuggestionsData.length;
            if (!count) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                var next = shareEmailActiveIndex + 1;
                if (next >= count) next = 0;
                setShareEmailActiveSuggestion(next);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                var prev = shareEmailActiveIndex - 1;
                if (prev < 0) prev = count - 1;
                setShareEmailActiveSuggestion(prev);
            } else if (event.key === 'Enter' && shareEmailActiveIndex >= 0) {
                event.preventDefault();
                selectShareEmailSuggestion(shareEmailActiveIndex);
            } else if (event.key === 'Escape') {
                hideShareEmailSuggestions();
            }
        });

        input.addEventListener('blur', function () {
            window.setTimeout(hideShareEmailSuggestions, 150);
        });
    }

    document.addEventListener('DOMContentLoaded', initShareEmailAutocomplete);

    document.querySelectorAll('.doc-share-btn').forEach(function (shareBtn) {
        shareBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var docId = shareBtn.getAttribute('data-share-id');
            closeDocActionsMenu(docId);
            openShareModal(
                docId,
                shareBtn.getAttribute('data-share-file') || 'Document',
                shareBtn.getAttribute('data-share-project') || ''
            );
        });
    });

    document.getElementById('share-modal-form')?.addEventListener('submit', function () {
        var sendBtn = document.getElementById('share-modal-send-btn');
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeShareModal();
        }
    });

    @if($shareErrorDocId)
        document.addEventListener('DOMContentLoaded', function () {
            openShareModal(
                '{{ $shareErrorDocId }}',
                @json($shareContext['file_name'] ?? 'Document'),
                @json($shareContext['project_number'] ?? '')
            );
            var errorEl = document.getElementById('share-modal-error');
            if (errorEl) {
                errorEl.style.display = 'block';
                errorEl.textContent = @json($shareErrorMessage);
            }
        });
    @endif
</script>

@endsection