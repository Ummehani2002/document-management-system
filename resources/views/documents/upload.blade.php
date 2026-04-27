@extends('layouts.app')

@section('content')

<h2>Upload Documents</h2>

<style>
    .upload-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(280px, 1fr));
        gap: 16px;
    }
    .upload-grid .full-width {
        grid-column: 1 / -1;
    }
    .upload-grid label {
        display: block;
        margin-bottom: 6px;
        font-weight: 400;
    }
    .upload-grid input,
    .upload-grid select {
        width: 100%;
        padding: 10px;
    }
    @media (max-width: 900px) {
        .upload-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

@if($entities->isEmpty() || $projects->isEmpty())
    <div class="card" style="margin-bottom: 20px; padding: 12px; background: #fffbeb; border-color: #fcd34d;">
        <strong>No master data yet.</strong> Add at least one <a href="{{ route('entities.create') }}">Entity</a> and one <a href="{{ route('projects.create') }}">Project</a> in Project Master first.
    </div>
@endif

@php
    $selectedUploadMode = old('upload_mode', $mode ?? '');
    $selectedMainFolder = old('main_folder', '');
    $selectedDocumentType = old('document_type', '');
@endphp

@if($selectedUploadMode === '')
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-top: 0;">Choose Upload Type</h3>
      
        <div style="display:flex; gap:12px; flex-wrap: wrap;">
            <a href="{{ route('documents.upload', ['mode' => 'auto']) }}" style="text-decoration:none;">
                <button type="button">Auto Upload</button>
            </a>
            <a href="{{ route('documents.upload', ['mode' => 'manual']) }}" style="text-decoration:none;">
                <button type="button">Manual Upload</button>
            </a>
        </div>
    </div>
@else
    <div class="card" style="margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
        <div>
            <div style="margin-bottom:4px;">
                Upload mode: <strong>{{ $selectedUploadMode === 'manual' ? 'Manual' : 'Auto' }}</strong>
            </div>
            <div style="color:#64748b;">
                {{ $selectedUploadMode === 'manual' ? 'Select entity, project, category and folder manually.' : 'System classifies folder from OCR title after upload.' }}
            </div>
        </div>
        <a href="{{ route('documents.upload') }}">Change mode</a>
    </div>

    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" id="upload-form">
        @csrf
        <input type="hidden" name="upload_mode" value="{{ $selectedUploadMode }}">

        <div class="upload-grid">
            <div class="card">
                <label for="entity_id">Select Entity *</label>
                <select name="entity_id" id="entity_id" required>
                    <option value="">— Select Entity —</option>
                    @foreach($entities as $e)
                        <option value="{{ $e->id }}" {{ old('entity_id') == $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                    @endforeach
                </select>
                @if($entities->isEmpty())<p style="margin-top: 6px; color: #b45309;">Add an <a href="{{ route('entities.create') }}">Entity</a> first.</p>@endif
                @error('entity_id')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
            </div>
            <div class="card">
                <label for="project_id">Select Project *</label>
                <select name="project_id" id="project_id" required>
                    <option value="">— Select entity first, then pick a project —</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" data-entity="{{ $p->entity_id }}"
                            data-name="{{ e($p->project_name ?? '') }}"
                            data-client="{{ e($p->client_name ?? '') }}"
                            data-consultant="{{ e($p->consultant ?? '') }}"
                            data-pm="{{ e($p->project_manager ?? '') }}"
                            data-dc="{{ e($p->document_controller ?? '') }}"
                            {{ old('project_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->project_number }} — {{ $p->project_name }}
                        </option>
                    @endforeach
                </select>
                @if($projects->isEmpty())<p style="margin-top: 6px; color: #b45309;">Add a <a href="{{ route('projects.create') }}">Project</a> in Project Master first.</p>@endif
                @error('project_id')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
                <div id="project-details" style="margin-top: 12px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: none;">
                    <div style="display: block; margin-bottom: 8px;">Project details (from Project Master)</div>
                    <div>Project name: <span id="disp-name">—</span></div>
                    <div>Client: <span id="disp-client">—</span></div>
                    <div>Consultant: <span id="disp-consultant">—</span></div>
                    <div>Project Manager: <span id="disp-pm">—</span></div>
                    <div>Document Controller: <span id="disp-dc">—</span></div>
                </div>
            </div>

            <div class="card">
                <label for="discipline_id">Discipline</label>
                <select name="discipline_id" id="discipline_id">
                    <option value="">— None —</option>
                    @foreach($disciplines ?? [] as $d)
                        <option value="{{ $d->id }}" {{ (string) old('discipline_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                </select>
                @if(($disciplines ?? collect())->isEmpty())
                    <p style="margin-top: 8px; color: #64748b;">No disciplines yet. Add them in <a href="{{ route('disciplines.index') }}">Discipline Master</a>.</p>
                @endif
                @error('discipline_id')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
            </div>

            @if($selectedUploadMode === 'manual')
                <div class="card">
                    <label for="main_folder">Category *</label>
                    <select name="main_folder" id="main_folder" required>
                        <option value="">— Select Category —</option>
                        @foreach(array_keys($folderTree ?? []) as $mainFolderName)
                            <option value="{{ $mainFolderName }}" {{ $selectedMainFolder === $mainFolderName ? 'selected' : '' }}>
                                {{ $mainFolderName }}
                            </option>
                        @endforeach
                    </select>
                    @error('main_folder')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
                </div>

                <div class="card">
                    <label for="document_type">Folder *</label>
                    <select name="document_type" id="document_type" required>
                        <option value="">— Select Folder —</option>
                    </select>
                    @error('document_type')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="card full-width">
                <label for="documents_input">Choose files (PDF/Word/Excel) *</label>
                <input type="file" name="documents[]" id="documents_input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                @if($selectedUploadMode === 'auto')

                @endif
                @error('documents')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
            </div>
        </div>

        <button type="submit">Upload</button>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectedUploadMode = @json($selectedUploadMode);
        var folderTree = @json($folderTree ?? []);
        var selectedDocumentType = @json($selectedDocumentType);
        var entitySelect = document.getElementById('entity_id');
        var projectSelect = document.getElementById('project_id');
        var detailsBox = document.getElementById('project-details');
        var mainFolderSelect = document.getElementById('main_folder');
        var documentTypeSelect = document.getElementById('document_type');
        if (!entitySelect || !projectSelect) return;

        var selectedProjectId = projectSelect.value || '';
        var projectOptions = Array.from(projectSelect.querySelectorAll('option[data-entity]')).map(function(opt) {
            return {
                value: opt.value,
                entityId: opt.getAttribute('data-entity'),
                text: opt.textContent,
                name: opt.getAttribute('data-name') || '—',
                client: opt.getAttribute('data-client') || '—',
                consultant: opt.getAttribute('data-consultant') || '—',
                pm: opt.getAttribute('data-pm') || '—',
                dc: opt.getAttribute('data-dc') || '—'
            };
        });

        function filterProjects() {
            var entityId = entitySelect.value;
            projectSelect.innerHTML = '<option value="">— Select project by number —</option>';
            projectOptions.forEach(function(opt) {
                if (opt.entityId !== entityId) return;
                var option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.text;
                option.setAttribute('data-name', opt.name);
                option.setAttribute('data-client', opt.client);
                option.setAttribute('data-consultant', opt.consultant);
                option.setAttribute('data-pm', opt.pm);
                option.setAttribute('data-dc', opt.dc);
                if (selectedProjectId && selectedProjectId === opt.value) {
                    option.selected = true;
                }
                projectSelect.appendChild(option);
            });
            showProjectDetails();
        }

        function showProjectDetails() {
            var opt = projectSelect.options[projectSelect.selectedIndex];
            if (!opt || !opt.value) {
                detailsBox.style.display = 'none';
                return;
            }
            detailsBox.style.display = 'block';
            document.getElementById('disp-name').textContent = opt.getAttribute('data-name') || '—';
            document.getElementById('disp-client').textContent = opt.getAttribute('data-client') || '—';
            document.getElementById('disp-consultant').textContent = opt.getAttribute('data-consultant') || '—';
            document.getElementById('disp-pm').textContent = opt.getAttribute('data-pm') || '—';
            document.getElementById('disp-dc').textContent = opt.getAttribute('data-dc') || '—';
        }

        function renderDocumentTypeOptions() {
            if (!mainFolderSelect || !documentTypeSelect) return;
            var selectedMain = mainFolderSelect.value;
            var folders = folderTree[selectedMain] || [];
            documentTypeSelect.innerHTML = '<option value="">— Select Folder —</option>';
            folders.forEach(function(folderName) {
                var option = document.createElement('option');
                option.value = folderName;
                option.textContent = folderName;
                if (selectedDocumentType && selectedDocumentType === folderName) {
                    option.selected = true;
                }
                documentTypeSelect.appendChild(option);
            });
            if (!folders.length) {
                selectedDocumentType = '';
            }
        }

        entitySelect.addEventListener('change', function() {
            selectedProjectId = '';
            filterProjects();
        });
        projectSelect.addEventListener('change', showProjectDetails);
        if (mainFolderSelect) {
            mainFolderSelect.addEventListener('change', function() {
                selectedDocumentType = '';
                renderDocumentTypeOptions();
            });
        }

        if (entitySelect.value) {
            filterProjects();
        } else {
            showProjectDetails();
        }
        renderDocumentTypeOptions();

    });
    </script>
@endif
@endsection
