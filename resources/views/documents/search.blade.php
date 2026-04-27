@extends('layouts.app')

@section('content')

<h2>Search Documents</h2>
@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif
@if(empty($fromSidebar) || empty(request('project_id')) || empty(request('document_type')))

@endif
@if(!empty($needsProjectSelection))
    <h3 style="margin-top: 0;">Select Project</h3>
    <p style="color: #64748b; margin-top: 0;">Selected subfolder: <strong>{{ request('document_type') }}</strong></p>
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

            function filterProjects() {
                var entityId = entitySelect.value;
                projectSelect.innerHTML = '<option value="">Select project</option>';
                allProjects.forEach(function (project) {
                    if (entityId && project.entityId !== entityId) return;
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
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; align-items: flex-end; margin-bottom: 16px;">
                <div style="min-width: 180px;">
                    <label for="keyword" style="display: block; margin-bottom: 4px; font-weight: 500;">Keyword</label>
                    <input type="text" name="keyword" id="keyword" placeholder="Search in text..." value="{{ old('keyword', $keyword ?? '') }}" style="width: 100%; padding: 8px 12px;">
                </div>
                <div style="min-width: 160px;">
                    <label for="entity_id" style="display: block; margin-bottom: 4px; font-weight: 500;">Entity (folder)</label>
                    <select name="entity_id" id="entity_id" style="width: 100%; padding: 8px 12px;">
                        <option value="">All entities</option>
                        @foreach($entities ?? [] as $e)
                            <option value="{{ $e->id }}" {{ (int) request('entity_id') === $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 200px;">
                    <label for="project_id" style="display: block; margin-bottom: 4px; font-weight: 500;">Project (folder)</label>
                    <select name="project_id" id="project_id" style="width: 100%; padding: 8px 12px;">
                        <option value="">All projects</option>
                        @foreach($projects ?? [] as $p)
                            <option value="{{ $p->id }}" {{ (int) request('project_id') === $p->id ? 'selected' : '' }}>
                                {{ $p->project_name }} ({{ $p->project_number }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 140px;">
                    <label for="discipline" style="display: block; margin-bottom: 4px; font-weight: 500;">Discipline (folder)</label>
                    <select name="discipline" id="discipline" style="width: 100%; padding: 8px 12px;">
                        <option value="">All disciplines</option>
                        @foreach($disciplines ?? [] as $d)
                            <option value="{{ $d }}" {{ request('discipline') === $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 140px;">
                    <label for="document_type" style="display: block; margin-bottom: 4px; font-weight: 500;">Doc type (folder)</label>
                    <select name="document_type" id="document_type" style="width: 100%; padding: 8px 12px;">
                        <option value="">All types</option>
                        @foreach($documentTypes ?? [] as $t)
                            <option value="{{ $t }}" {{ request('document_type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit">Search</button>
            </div>
        </form>
    @endif
@endif

@if(isset($documents) && $documents !== null)
    @php
        $documentsCount = method_exists($documents, 'total')
            ? $documents->total()
            : $documents->count();
    @endphp

    <p style="margin: 0 0 12px; color: #64748b;">
        File count: <span style="color:#212d3e;">{{ $documentsCount }}</span>
    </p>

    @php
        $parts = array_filter([
            $keyword !== '' ? '"' . e($keyword) . '"' : null,
            request('entity_id') ? 'entity' : null,
            request('project_id') ? 'project' : null,
            request('discipline') ? 'discipline: ' . e(request('discipline')) : null,
            request('document_type') ? 'type: ' . e(request('document_type')) : null,
        ]);
    @endphp
    @if(empty($fromSidebar) || empty(request('project_id')) || empty(request('document_type')))
        <h4>Results: {{ count($parts) ? implode(' · ', $parts) : 'all documents' }}</h4>
    @endif

    @if(!empty($fromSidebar) && request('project_id') && request('document_type'))
        @php
            $folderTree = [
                'Financial Documents' => ['Bank Gurantees','Invoice','Payment Voucher','Proforma Invoice','Receipt Voucher','Sales Credit Note','Supplier Delivery Note','Supplier Invoice','Supplier Time Sheets'],
                'General Correspondence' => ['Incoming Or Outgoing Letter','Internal Memo','KPI Report','Monthly Report','Payment Certificate','Project Award Notification','Snags','Spare Parts'],
                'Project Correspondence' => ['Defect Liability Certificate','Engineers Correspondences','Engineers Instruction','MOM','NCR','Operation And Maintenance Manual','Payment Application','Quality Observation Report','Request For Information','Site Observation Report','Site Incident Report','Taking Over Certificate','Testing And Commissioning','Variation','Warranty By Us','Change Request','Design Calculation','Confirmation Of Verbal Instruction','Project Commercial Documents'],
                'Purchase Documents' => ['Catalogs','Delivery Order','Enquireis','Good Receipt Note','Material Issue Note','Material Return Note','Purchase Order','Purchase Request','Quotations','Sales Order','Trade License certificate','VAT Registration Certificate','Vendor Registration certificate'],
                'Transmittals Documents' => ['As Built Drawing Submittal','Material Submittal','Material Inspection Request','Method Statement','Prequalification','Shop Drawing','Work Inspection','Document Transmittal','Material Sample'],
            ];
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
                    Folder: <strong>{{ $activeType }}</strong>
                </p>
            </form>

            <section>
                <div class="card" style="padding: 0; overflow-x: auto; margin-top: 0;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 1150px;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; background: #212d3e; color: #fff;">
                                <th style="text-align: left; padding: 10px;">Name</th>
                                <th style="text-align: left; padding: 10px;">Reference No</th>
                                <th style="text-align: left; padding: 10px;">Subject</th>
                                <th style="text-align: left; padding: 10px;">Project Discipline</th>
                                <th style="text-align: left; padding: 10px;">Project Number</th>
                                <th style="text-align: left; padding: 10px;">Project Name</th>
                                <th style="text-align: left; padding: 10px;">Project Client</th>
                                <th style="text-align: left; padding: 10px;">Project Consultant</th>
                                <th style="text-align: left; padding: 10px;">Modified</th>
                                <th style="text-align: left; padding: 10px;">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $doc)
                                @php
                                    $meta = \App\Services\DocumentFilenameParser::extractReferenceAndSubject($doc->ocr_text, $doc->file_name);
                                    $referenceNo = $meta['reference_no'] ?? '—';
                                    $subject = $meta['subject'] ?? '—';
                                @endphp
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 10px;">
                                        @if(!empty($doc->file_available))
                                            <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank">{{ $doc->file_name }}</a>
                                        @else
                                            <span>{{ $doc->file_name }}</span>
                                            <div style="color:#b91c1c; font-size:0.8rem; margin-top:4px;">File unavailable in storage</div>
                                        @endif
                                    </td>
                                    <td style="padding: 10px;">{{ $referenceNo }}</td>
                                    <td style="padding: 10px;">{{ $subject }}</td>
                                    <td style="padding: 10px;">{{ $doc->discipline ?: '—' }}</td>
                                    <td style="padding: 10px;">{{ $doc->project?->project_number ?? '—' }}</td>
                                    <td style="padding: 10px;">{{ $doc->project?->project_name ?? '—' }}</td>
                                    <td style="padding: 10px;">{{ $doc->project?->client_name ?? '—' }}</td>
                                    <td style="padding: 10px;">{{ $doc->project?->consultant ?? '—' }}</td>
                                    <td style="padding: 10px;">{{ optional($doc->updated_at)->format('M d, Y') ?: '—' }}</td>
                                    <td style="padding: 10px; min-width: 190px;">
                                        @if(!empty($doc->file_available))
                                            <button type="button" style="padding:6px 10px; margin-right:6px;" onclick="toggleInlinePreview('{{ $doc->id }}', '{{ route('documents.view', ['id' => $doc->id]) }}')">View here</button>
                                            <button type="button" style="padding:6px 10px;" onclick="toggleShareForm('{{ $doc->id }}')">Share</button>
                                            <div id="share-box-{{ $doc->id }}" style="display:{{ $errors->has('share_email_' . $doc->id) ? 'block' : 'none' }}; margin-top:8px;">
                                                <form method="POST" action="{{ route('documents.share', ['id' => $doc->id]) }}">
                                                    @csrf
                                                    <input
                                                        type="email"
                                                        name="email"
                                                        placeholder="Enter email"
                                                        value="{{ old('email') }}"
                                                        required
                                                        style="width: 100%; padding: 6px 8px; margin: 0 0 6px;"
                                                    >
                                                    <button type="submit" style="padding:6px 10px;">Send</button>
                                                </form>
                                                @error('share_email_' . $doc->id)
                                                    <p style="margin:6px 0 0; color:#b91c1c; font-size:0.82rem;">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @else
                                            <span style="color:#94a3b8;">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                                @if(!empty($doc->file_available))
                                    <tr id="preview-row-{{ $doc->id }}" style="display:none; border-bottom: 1px solid #e2e8f0;">
                                        <td colspan="10" style="padding: 10px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                                <strong>Inline Preview</strong>
                                                <button type="button" style="padding:4px 10px;" onclick="closeInlinePreview('{{ $doc->id }}')">Close preview</button>
                                            </div>
                                            <iframe
                                                id="preview-frame-{{ $doc->id }}"
                                                src=""
                                                title="Preview {{ $doc->file_name }}"
                                                style="width:100%; height:75vh; border:1px solid #cbd5e1; border-radius:6px;"
                                            ></iframe>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="10" style="padding: 14px;">No files found in this folder for selected project.</td>
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

                function filterProjects() {
                    var entityId = entitySelect.value;
                    projectSelect.innerHTML = '<option value="">Select project</option>';
                    allProjects.forEach(function (project) {
                        if (entityId && project.entityId !== entityId) return;
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
        @forelse($documents as $doc)
            @php
                $displayType = \App\Services\DocumentFilenameParser::parse($doc->file_name)['document_category'] ?? ($doc->document_type ?: '—');
            @endphp
            <div class="card">
                <strong>{{ $doc->file_name }}</strong>
                <span style="color: #64748b; font-weight: normal;"> — File</span>
                <br><br>

                <strong>Folder:</strong>
                <span style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 4px; display: inline-block; margin: 4px 0;">
                    {{ $doc->entity?->name ?? '—' }} / {{ $doc->project?->project_number ?? '—' }} / {{ $displayType }}
                </span>
                <br>

                @if($doc->project)
                    <strong>Project:</strong> {{ $doc->project->project_name }} ({{ $doc->project->project_number }})<br>
                @endif
                <br>
                @if(!empty($doc->file_available))
                    <a href="{{ route('documents.download', ['id' => $doc->id]) }}">Download file</a>
                    &nbsp;|&nbsp;
                    <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank" rel="noopener">Open in new tab</a>
                    &nbsp;|&nbsp;
                    <button type="button" style="padding:4px 10px;" onclick="toggleInlinePreview('{{ $doc->id }}', '{{ route('documents.view', ['id' => $doc->id]) }}')">View here</button>
                    &nbsp;|&nbsp;
                    <button type="button" style="padding:4px 10px;" onclick="toggleShareForm('{{ $doc->id }}')">Share</button>
                    <div id="share-box-{{ $doc->id }}" style="display:{{ $errors->has('share_email_' . $doc->id) ? 'block' : 'none' }}; margin-top:8px;">
                        <form method="POST" action="{{ route('documents.share', ['id' => $doc->id]) }}">
                            @csrf
                            <input
                                type="email"
                                name="email"
                                placeholder="Enter email"
                                value="{{ old('email') }}"
                                required
                                style="width: 100%; max-width: 320px; padding: 6px 8px; margin: 0 0 6px;"
                            >
                            <button type="submit" style="padding:6px 10px;">Send</button>
                        </form>
                        @error('share_email_' . $doc->id)
                            <p style="margin:6px 0 0; color:#b91c1c; font-size:0.82rem;">{{ $message }}</p>
                        @enderror
                    </div>
                    <div id="preview-row-{{ $doc->id }}" style="display:none; margin-top:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <strong>Inline Preview</strong>
                            <button type="button" style="padding:4px 10px;" onclick="closeInlinePreview('{{ $doc->id }}')">Close preview</button>
                        </div>
                        <iframe
                            id="preview-frame-{{ $doc->id }}"
                            src=""
                            title="Preview {{ $doc->file_name }}"
                            style="width:100%; height:75vh; border:1px solid #cbd5e1; border-radius:6px;"
                        ></iframe>
                    </div>
                @else
                    <span style="color:#b91c1c;">File unavailable in storage</span>
                @endif
            </div>
        @empty
            <p>No documents found.</p>
        @endforelse
        {{ $documents->links() }}
    @endif
@endif

<script>
    function toggleInlinePreview(documentId, pdfUrl) {
        var previewRow = document.getElementById('preview-row-' + documentId);
        var frame = document.getElementById('preview-frame-' + documentId);
        if (!previewRow || !frame) return;

        var opening = previewRow.style.display === 'none' || previewRow.style.display === '';

        document.querySelectorAll('[id^="preview-row-"]').forEach(function (row) {
            row.style.display = 'none';
        });
        document.querySelectorAll('[id^="preview-frame-"]').forEach(function (f) {
            f.src = '';
        });

        if (opening) {
            frame.src = pdfUrl;
            previewRow.style.display = 'table-row';
            if (previewRow.tagName.toLowerCase() !== 'tr') {
                previewRow.style.display = 'block';
            }
        }
    }

    function closeInlinePreview(documentId) {
        var previewRow = document.getElementById('preview-row-' + documentId);
        var frame = document.getElementById('preview-frame-' + documentId);
        if (previewRow) {
            previewRow.style.display = 'none';
        }
        if (frame) {
            frame.src = '';
        }
    }

    function toggleShareForm(documentId) {
        var el = document.getElementById('share-box-' + documentId);
        if (!el) return;
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
</script>

@endsection