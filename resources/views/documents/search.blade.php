@extends('layouts.app')

@section('content')

<h2>Search Documents</h2>
<p style="color: #64748b; font-size: 0.9rem; margin-bottom: 16px;">Keyword is searched in the <strong>first page</strong> of each PDF, file names, and folder names. Use the dropdowns to filter.</p>

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
        @if($totalDocuments > 0)
            <p style="margin-top: 8px; font-size: 0.9rem; color: #475569;">
                You have <strong>{{ $totalDocuments }}</strong> document(s) in the system.
                @if($documentsWithoutOcr > 0)
                    <strong>{{ $documentsWithoutOcr }}</strong> have no first-page text indexed yet. In your project folder run: <code style="background: #f1f5f9; padding: 2px 6px;">php artisan documents:index-ocr --sync</code> then search again.
                    <br><span style="font-size: 0.85rem; color: #64748b;">If you already ran that command, the first page may have no extractable text (e.g. scanned/image-only PDF). Keyword search only works on text.</span>
                @else
                    First-page text is indexed for all. Try <strong>Doc type: All types</strong> and <strong>Discipline: All disciplines</strong> — your documents may be under a different type than the one selected.
                @endif
            </p>
        @else
            <p style="margin-top: 8px; font-size: 0.9rem; color: #475569;">There are no documents yet. <a href="{{ route('documents.upload') }}">Upload PDFs</a> first, then run <code>php artisan documents:index-ocr --sync</code> to make them searchable.</p>
        @endif
    @endforelse

    {{ $documents->links() }}
@endif

@endsection