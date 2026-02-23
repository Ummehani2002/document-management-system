@extends('layouts.app')

@section('content')

<h2>Upload Documents</h2>


@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

@if($entities->isEmpty() || $projects->isEmpty())
    <div class="card" style="margin-bottom: 20px; padding: 12px; background: #fffbeb; border-color: #fcd34d;">
        <strong>No master data yet.</strong> Add at least one <a href="{{ route('entities.create') }}">Entity</a> and one <a href="{{ route('projects.create') }}">Project</a> in Project Master first.
    </div>
@endif

<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" id="upload-form">
    @csrf

    <div class="card" style="margin-bottom: 20px;">
       
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
      
        <label style="display: block; margin-bottom: 6px; font-weight: 600;">Select Project *</label>
        <select name="project_id" id="project_id" required style="width: 100%; max-width: 400px; padding: 10px;">
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
        <div id="project-details" style="margin-top: 12px; padding: 12px; background: #f1f5f9; border-radius: 6px; display: none;">
            <strong style="display: block; margin-bottom: 8px;">Project details (from Project Master)</strong>
            <div><strong>Project name:</strong> <span id="disp-name">—</span></div>
            <div><strong>Client:</strong> <span id="disp-client">—</span></div>
            <div><strong>Consultant:</strong> <span id="disp-consultant">—</span></div>
            <div><strong>Project Manager:</strong> <span id="disp-pm">—</span></div>
            <div><strong>Document Controller:</strong> <span id="disp-dc">—</span></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <label style="display: block; margin-bottom: 6px; font-weight: 600;">Folder (category) *</label>
        <select name="document_category" id="document_category" required style="width: 100%; max-width: 400px; padding: 10px;">
            <option value="">— Select folder —</option>
            @foreach($documentCategories as $cat)
                <option value="{{ $cat }}" {{ old('document_category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
        </select>
    
        @error('document_category')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <label style="display: block; margin-bottom: 6px; font-weight: 600;">Choose PDF files *</label>
        <input type="file" name="documents[]" id="documents_input" multiple accept=".pdf" required style="width: 100%; padding: 10px;">
        <p id="suggest-msg" style="margin-top: 8px; font-size: 0.85rem; color: #0f766e; display: none;"></p>
        @error('documents')<p style="margin-top: 6px; color: #b91c1c;">{{ $message }}</p>@enderror
    </div>

    <button type="submit">Upload</button>


</form>

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
            if (opt.entityId === entityId) {
                var o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.text;
                o.setAttribute('data-name', opt.name);
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
            document.getElementById('disp-name').textContent = opt.getAttribute('data-name') || '—';
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

    var suggestMsg = document.getElementById('suggest-msg');
    var fileInput = document.getElementById('documents_input');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            var files = this.files;
            if (!files || files.length === 0) return;
            var first = files[0].name;
            if (!first || !first.toLowerCase().endsWith('.pdf')) return;
            suggestMsg.style.display = 'none';
            suggestMsg.textContent = '';
            var url = '{{ route("documents.suggest") }}?filename=' + encodeURIComponent(first);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.entity_id && data.project_id) {
                        entitySelect.value = data.entity_id;
                        filterProjects();
                        setTimeout(function() {
                            projectSelect.value = data.project_id;
                            showProjectDetails();
                        }, 50);
                    }
                    if (data.document_category) {
                        var catSelect = document.getElementById('document_category');
                        if (catSelect) {
                            var opt = Array.from(catSelect.options).find(function(o) { return o.value === data.document_category; });
                            if (opt) catSelect.value = data.document_category;
                        }
                    }
                    if (data.entity_id || data.document_category) {
                        suggestMsg.textContent = 'From title: ' + (data.project_number || '') + (data.document_category ? ' → folder ' + data.document_category : '') + '. Check and upload.';
                        suggestMsg.style.display = 'block';
                    }
                })
                .catch(function() {});
        });
    }
});
</script>
@endsection
