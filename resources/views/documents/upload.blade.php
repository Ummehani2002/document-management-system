@extends('layouts.app')

@section('content')

<h2>Upload Documents</h2>
<p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px;">Select Entity → Project number → see project details (DC, client, etc.) → choose folder category → upload. Files are saved in that folder.</p>

@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

@if($entities->isEmpty() || $projects->isEmpty())
    <div class="card" style="margin-bottom: 20px; padding: 12px; background: #fffbeb; border-color: #fcd34d;">
        <strong>No master data yet.</strong> Add at least one <a href="{{ route('entities.create') }}">Entity</a> and one <a href="{{ route('projects.create') }}">Project</a> (in Project Master) so you can select them below. You can still see all the fields.
    </div>
@endif

<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" id="upload-form">
    @csrf

    <div class="card" style="margin-bottom: 20px;">
        <span style="display: inline-block; background: #1e293b; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; margin-bottom: 8px;">Step 1</span>
        <label style="display: block; margin-bottom: 6px; font-weight: 600;">Select Entity *</label>
        <select name="entity_id" id="entity_id" required style="width: 100%; max-width: 400px; padding: 10px;">
            <option value="">— Select Entity —</option>
            @foreach($entities as $e)
                <option value="{{ $e->id }}" {{ old('entity_id') == $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
            @endforeach
        </select>
        @if($entities->isEmpty())<p style="margin-top: 6px; color: #b45309;">Add an <a href="{{ route('entities.create') }}">Entity</a> first.</p>@endif
        @error('entity_id')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <span style="display: inline-block; background: #1e293b; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; margin-bottom: 8px;">Step 2</span>
        <label style="display: block; margin-bottom: 6px; font-weight: 600;">Select Project Number *</label>
        <select name="project_id" id="project_id" required style="width: 100%; max-width: 400px; padding: 10px;">
            <option value="">— Select Entity first, then pick project by number —</option>
            @foreach($projects as $p)
                <option value="{{ $p->id }}" data-entity="{{ $p->entity_id }}"
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
        <div id="project-details" style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 6px; display: none;">
            <strong style="display: block; margin-bottom: 8px;">Project details (auto-filled)</strong>
            <div><strong>Client:</strong> <span id="disp-client">—</span></div>
            <div><strong>Consultant:</strong> <span id="disp-consultant">—</span></div>
            <div><strong>Project Manager:</strong> <span id="disp-pm">—</span></div>
            <div><strong>Document Controller (DC):</strong> <span id="disp-dc">—</span></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <span style="display: inline-block; background: #1e293b; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; margin-bottom: 8px;">Step 3</span>
        <label style="display: block; margin-bottom: 6px; font-weight: 600;">Folder (document category) *</label>
        <select name="document_category" id="document_category" required style="width: 100%; max-width: 400px; padding: 10px;">
            <option value="">— Select folder / category —</option>
            @foreach($documentCategories as $cat)
                <option value="{{ $cat }}" {{ old('document_category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
        </select>
        <small style="color: #64748b;">File will be saved in: Entity / Project number / this folder</small>
        @error('document_category')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <span style="display: inline-block; background: #1e293b; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; margin-bottom: 8px;">Step 4</span>
        <label style="display: block; margin-bottom: 6px; font-weight: 600;">Choose PDF files *</label>
        <input type="file" name="documents[]" multiple accept=".pdf" required style="width: 100%; padding: 10px;">
        @error('documents')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
    </div>

    <button type="submit">Upload</button>
</form>

<p style="margin-top: 16px; color: #64748b; font-size: 0.9rem;">
    Saved in folder: <strong>Entity / Project number / Document category</strong> (e.g. Main Company / PSE20231011 / Method Submittal). Search results show the PDF with its folder name.
</p>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var entitySelect = document.getElementById('entity_id');
    var projectSelect = document.getElementById('project_id');
    var detailsBox = document.getElementById('project-details');
    if (!entitySelect || !projectSelect) return;

    var projectOptions = Array.from(projectSelect.querySelectorAll('option[data-entity]')).map(function(opt) {
        return {
            value: opt.value,
            entityId: opt.getAttribute('data-entity'),
            text: opt.textContent,
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
            if (opt.entityId === entityId) {
                var o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.text;
                o.setAttribute('data-client', opt.client);
                o.setAttribute('data-consultant', opt.consultant);
                o.setAttribute('data-pm', opt.pm);
                o.setAttribute('data-dc', opt.dc);
                projectSelect.appendChild(o);
            }
        });
        showProjectDetails();
    }

    function showProjectDetails() {
        var opt = projectSelect.options[projectSelect.selectedIndex];
        if (!opt || !opt.value) {
            if (detailsBox) detailsBox.style.display = 'none';
            return;
        }
        if (detailsBox) {
            detailsBox.style.display = 'block';
            document.getElementById('disp-client').textContent = opt.getAttribute('data-client') || '—';
            document.getElementById('disp-consultant').textContent = opt.getAttribute('data-consultant') || '—';
            document.getElementById('disp-pm').textContent = opt.getAttribute('data-pm') || '—';
            document.getElementById('disp-dc').textContent = opt.getAttribute('data-dc') || '—';
        }
    }

    entitySelect.addEventListener('change', filterProjects);
    projectSelect.addEventListener('change', showProjectDetails);
    if (entitySelect.value) filterProjects();
    showProjectDetails();
});
</script>
@endsection
