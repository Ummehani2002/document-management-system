@extends('layouts.app')

@section('content')

<h2>Search Documents</h2>
<p style="color: #64748b; font-size: 0.9rem; margin-bottom: 16px;">Keyword matches <strong>content</strong> (PDF text), <strong>file name</strong>, and <strong>folder names</strong> (entity, project number, project name, document type). You can also filter by the dropdowns.</p>
@if(isset($documents) && $documents->total() === 0)
    <p style="color: #b45309; font-size: 0.9rem; margin-bottom: 12px; padding: 10px; background: #fffbeb; border-radius: 6px;">
        <strong>Tips:</strong>
        Keyword matches <strong>file names</strong> and document text. On Upload, select Entity and Project from the dropdowns so files are stored in the correct folder. Run <code>php artisan queue:work</code> for OCR text search.
    </p>
@endif

<form method="GET" action="{{ route('documents.search') }}" class="search-form">
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

@if(isset($documents))
    @php
        $parts = array_filter([
            $keyword !== '' ? '"' . e($keyword) . '"' : null,
            request('entity_id') ? 'entity' : null,
            request('project_id') ? 'project' : null,
            request('discipline') ? 'discipline: ' . e(request('discipline')) : null,
            request('document_type') ? 'type: ' . e(request('document_type')) : null,
        ]);
    @endphp
    <h4>Results: {{ count($parts) ? implode(' · ', $parts) : 'all documents' }}</h4>

    @forelse($documents as $doc)
        <div class="card">
            <strong>{{ $doc->file_name }}</strong>
            <span style="color: #64748b; font-weight: normal;"> — PDF</span>
            <br><br>

            <strong>Folder:</strong>
            <span style="background: #f1f5f9; padding: 6px 10px; border-radius: 4px; display: inline-block; margin: 4px 0;">
                {{ $doc->entity?->name ?? '—' }} / {{ $doc->project?->project_number ?? '—' }}@if($doc->document_type) / {{ $doc->document_type }}@endif
            </span>
            <br>
            <small style="color: #64748b;">{{ $doc->file_path }}</small><br><br>

            @if($doc->project)
                <strong>Project:</strong> {{ $doc->project->project_name }} ({{ $doc->project->project_number }})<br>
            @endif
            <br>
            <a href="{{ route('documents.download', $doc) }}">Download PDF</a>
            &nbsp;|&nbsp;
            <a href="{{ route('documents.download', $doc) }}" target="_blank">Open in new tab</a>
        </div>
    @empty
        <p>No documents found.</p>
    @endforelse

    {{ $documents->links() }}
@endif

@endsection