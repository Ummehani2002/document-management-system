@extends('layouts.app')

@section('content')

<h2>Upload Documents</h2>

<style>
    .upload-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(280px, 1fr));
        gap: 16px;
        align-items: start;
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
@if(request()->boolean('direct_ok'))
    <div class="success">Uploaded successfully.</div>
@endif
@if($errors->any())
    <div class="card" style="margin-bottom: 16px; border-color: #fecaca; background: #fef2f2; color: #991b1b;">
        <strong>Upload failed:</strong>
        <ul style="margin: 8px 0 0 18px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
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
            </div>

            <div id="project-details" class="card full-width" style="display: none;">
                <div style="display: block; margin-bottom: 8px;">Project details (from Project Master)</div>
                <div>Project name: <span id="disp-name">—</span></div>
                <div>Client: <span id="disp-client">—</span></div>
                <div>Consultant: <span id="disp-consultant">—</span></div>
                <div>Project Manager: <span id="disp-pm">—</span></div>
                <div>Document Controller: <span id="disp-dc">—</span></div>
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
                @if(!empty($directUploadEnabled))
                 
                @endif
                @if($selectedUploadMode === 'auto')

                @endif
                @error('documents')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
            </div>
        </div>

        <button type="submit" id="upload-submit-btn">Upload</button>
        <p id="upload-status-text" style="display:none; margin-top:8px; color:#334155;">Uploading... please wait.</p>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectedUploadMode = @json($selectedUploadMode);
        var directUploadEnabled = @json(!empty($directUploadEnabled));
        var directUploadMinBytes = @json((int) (($directUploadMinMb ?? 75) * 1024 * 1024));
        var presignUrl = @json(route('documents.upload.presign'));
        var completeUrl = @json(route('documents.upload.complete'));
        var chunkInitUrl = @json(route('documents.upload.chunk-init'));
        var chunkStoreUrl = @json(route('documents.upload.chunk'));
        var chunkFinishUrl = @json(route('documents.upload.chunk-finish'));
        var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
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

        var uploadForm = document.getElementById('upload-form');
        var submitBtn = document.getElementById('upload-submit-btn');
        var uploadStatus = document.getElementById('upload-status-text');

        if (uploadForm && submitBtn) {
            uploadForm.addEventListener('submit', async function (e) {
                var filesInput = document.getElementById('documents_input');
                var files = filesInput && filesInput.files ? Array.from(filesInput.files) : [];
                var needsDirect = directUploadEnabled && files.some(function (f) { return f.size > directUploadMinBytes; });

                if (!needsDirect) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Uploading...';
                    if (uploadStatus) uploadStatus.style.display = 'block';
                    return;
                }

                e.preventDefault();

                if (!csrfToken) {
                    alert('Missing CSRF token. Reload the page.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                if (uploadStatus) {
                    uploadStatus.style.display = 'block';
                    uploadStatus.textContent = 'Uploading... please wait.';
                }

                try {
                    async function readJsonResponse(res, step) {
                        var text = await res.text();
                        try {
                            return JSON.parse(text);
                        } catch (e2) {
                            throw new Error(step + ': HTTP ' + res.status + ' — ' + (text ? text.slice(0, 400) : res.statusText));
                        }
                    }

                    function buildPresignBody(file) {
                        var body = {
                            upload_mode: uploadForm.querySelector('[name="upload_mode"]').value,
                            entity_id: entitySelect.value,
                            project_id: projectSelect.value,
                            filename: file.name,
                            file_size: file.size,
                            content_type: file.type || 'application/octet-stream'
                        };
                        var disc = document.getElementById('discipline_id');
                        if (disc && disc.value) {
                            body.discipline_id = parseInt(disc.value, 10);
                        }
                        if (mainFolderSelect && documentTypeSelect) {
                            body.main_folder = mainFolderSelect.value;
                            body.document_type = documentTypeSelect.value;
                        }
                        return body;
                    }

                    function setUploadProgress(msg) {
                        if (uploadStatus) {
                            uploadStatus.style.display = 'block';
                            uploadStatus.textContent = msg;
                        }
                        if (submitBtn) {
                            submitBtn.textContent = msg.length > 42 ? msg.slice(0, 40) + '…' : msg;
                        }
                    }

                    for (var i = 0; i < files.length; i++) {
                        var file = files[i];
                        var presignBody = buildPresignBody(file);
                        var useChunked = file.size > directUploadMinBytes;

                        if (useChunked) {
                            setUploadProgress('File ' + (i + 1) + '/' + files.length + ': starting…');
                            var initRes;
                            try {
                                initRes = await fetch(chunkInitUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify(presignBody)
                                });
                            } catch (err) {
                                throw new Error('Chunk init: ' + (err && err.message ? err.message : String(err)) + ' — check network / login session.');
                            }
                            var initJson = await readJsonResponse(initRes, 'Chunk init');
                            if (!initRes.ok) {
                                throw new Error('Chunk init: ' + (initJson.message || JSON.stringify(initJson.errors || initJson) || initRes.status));
                            }
                            var chunkToken = initJson.token;
                            var chunkSize = parseInt(initJson.chunk_size, 10) || 0;
                            var totalChunks = parseInt(initJson.total_chunks, 10) || 0;
                            if (!chunkToken || chunkSize < 1 || totalChunks < 1) {
                                throw new Error('Chunk init: invalid response from server.');
                            }

                            for (var c = 0; c < totalChunks; c++) {
                                setUploadProgress('File ' + (i + 1) + '/' + files.length + ': uploading part ' + (c + 1) + '/' + totalChunks + '…');
                                var start = c * chunkSize;
                                var end = Math.min(start + chunkSize, file.size);
                                var blob = file.slice(start, end);
                                var fd = new FormData();
                                fd.append('token', chunkToken);
                                fd.append('index', String(c));
                                fd.append('chunk', blob, 'chunk.bin');
                                var upRes;
                                try {
                                    upRes = await fetch(chunkStoreUrl, {
                                        method: 'POST',
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': csrfToken,
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        credentials: 'same-origin',
                                        body: fd
                                    });
                                } catch (err) {
                                    throw new Error('Chunk ' + (c + 1) + '/' + totalChunks + ': ' + (err && err.message ? err.message : String(err)));
                                }
                                var upJson = await readJsonResponse(upRes, 'Chunk ' + (c + 1) + '/' + totalChunks);
                                if (!upRes.ok) {
                                    throw new Error('Chunk ' + (c + 1) + '/' + totalChunks + ': ' + (upJson.message || upRes.status));
                                }
                            }

                            setUploadProgress('File ' + (i + 1) + '/' + files.length + ': finalizing on server (usually quick)…');
                            var finRes;
                            try {
                                finRes = await fetch(chunkFinishUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ token: chunkToken })
                                });
                            } catch (err) {
                                throw new Error('Chunk finish: ' + (err && err.message ? err.message : String(err)));
                            }
                            var finJson = await readJsonResponse(finRes, 'Chunk finish');
                            if (!finRes.ok) {
                                throw new Error('Chunk finish: ' + (finJson.message || finRes.status));
                            }
                            continue;
                        }

                        var pr;
                        try {
                            pr = await fetch(presignUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify(presignBody)
                            });
                        } catch (err) {
                            throw new Error('Step 1 (presign): ' + (err && err.message ? err.message : String(err)) + ' — check network / login session.');
                        }
                        var presignJson = await readJsonResponse(pr, 'Step 1 (presign)');
                        if (!pr.ok) {
                            throw new Error('Step 1 (presign): ' + (presignJson.message || JSON.stringify(presignJson.errors || presignJson) || pr.status));
                        }

                        var ct = (presignJson.headers && presignJson.headers['Content-Type']) || file.type || 'application/pdf';
                        var putRes;
                        try {
                            putRes = await fetch(presignJson.upload_url, {
                                method: presignJson.method || 'PUT',
                                headers: { 'Content-Type': ct },
                                body: file,
                                mode: 'cors',
                                cache: 'no-store'
                            });
                        } catch (err) {
                            throw new Error(
                                'Step 2 (upload to R2): request was blocked or failed before a response — most often R2 CORS. ' +
                                'In Cloudflare R2 → your bucket → Settings → CORS, allow method PUT and origin: ' +
                                window.location.origin
                            );
                        }
                        if (!putRes.ok) {
                            var putErrText = await putRes.text().catch(function () { return ''; });
                            throw new Error('Step 2 (upload to R2): HTTP ' + putRes.status + (putErrText ? ' — ' + putErrText.slice(0, 300) : '') + '. If 403, check signature/CORS; if 0, CORS preflight failed.');
                        }

                        var cr;
                        try {
                            cr = await fetch(completeUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ token: presignJson.token })
                            });
                        } catch (err) {
                            throw new Error('Step 3 (register): ' + (err && err.message ? err.message : String(err)));
                        }
                        var completeJson = await readJsonResponse(cr, 'Step 3 (register)');
                        if (!cr.ok) {
                            throw new Error('Step 3 (register): ' + (completeJson.message || cr.status));
                        }
                    }

                    var redirectBase = @json(url('/upload'));
                    window.location.href = redirectBase + (selectedUploadMode ? ('?mode=' + encodeURIComponent(selectedUploadMode) + '&') : '?') + 'direct_ok=1';
                } catch (err) {
                    alert(err && err.message ? err.message : String(err));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload';
                    if (uploadStatus) {
                        uploadStatus.style.display = 'none';
                        uploadStatus.textContent = 'Uploading... please wait.';
                    }
                }
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
