@extends('layouts.app')

@section('content')
    <h2>Project Dashboard</h2>

    <form method="get" action="{{ route('project-dashboard') }}" class="card" style="margin-bottom: 20px;">
        <label for="q" style="display: block; margin-bottom: 8px; font-weight: 400;">Project number or name</label>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <input
                type="text"
                name="q"
                id="q"
                value="{{ $searchQuery ?? $projectNumberQuery ?? '' }}"
                placeholder="e.g. MH-0026 or project name"
                style="max-width: 360px; margin: 0;"
                autocomplete="off"
            >
            <button type="submit">Show PDFs</button>
        </div>
        <p style="margin: 10px 0 0; color: #64748b; font-size: 0.9rem;">
            Matches project number (exact or partial) or project name (partial).
        </p>
    </form>

    @if($searchQuery !== '' && $projects->isNotEmpty())
        <div class="card dms-grid-wrap" style="margin-bottom: 20px;">
            <p style="margin: 0 0 12px; padding: 16px 16px 0; color: #334155;">Several projects match <strong>{{ $searchQuery }}</strong>. Pick one:</p>
            <table class="dms-grid-table min-w-sm">
                <thead>
                    <tr>
                        <th>Project number</th>
                        <th>Project name</th>
                        <th>Entity</th>
                        <th class="text-right">PDFs</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projects as $p)
                        <tr>
                            <td>{{ $p->project_number }}</td>
                            <td>{{ $p->project_name }}</td>
                            <td>{{ $p->entity?->name ?? '—' }}</td>
                            <td class="text-right">{{ (int) ($p->documents_count ?? 0) }}</td>
                            <td class="text-right">
                                <a href="{{ route('project-dashboard', ['project_id' => $p->id]) }}">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($searchQuery !== '' && !$project && $projects->isEmpty())
        <div class="card" style="border-color: #e2e8f0;">
            <p style="margin: 0;">No project found matching <strong>{{ $searchQuery }}</strong>. Try another number or name, or check <a href="{{ route('projects.index') }}">Project Master</a>.</p>
        </div>
    @elseif($project)
        <div class="card" style="margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: baseline; justify-content: space-between;">
            <p style="margin: 0; color: #334155;">
                <span>{{ $project->project_number }}</span>
                <span style="color: #64748b;"> — </span>
                <span>{{ $project->project_name }}</span>
                @if($project->entity)
                    <span style="color: #64748b;"> — {{ $project->entity->name }}</span>
                @endif
            </p>
            <p style="margin: 0; color: #64748b;">
                PDFs in this project: <span style="color: #212d3e;">{{ $pdfCount }}</span>
            </p>
        </div>

        @if($documents->isEmpty())
            <div class="card">
                <p style="margin: 0;">No PDFs uploaded for this project yet.</p>
            </div>
        @else
            <div class="card dms-grid-wrap">
                <table class="dms-grid-table min-w-md">
                    <thead>
                        <tr>
                            <th>PDF</th>
                            <th>Category / folder</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $doc)
                            @php
                                $docType = $doc->document_type;
                                if ($docType === null || trim((string) $docType) === '') {
                                    $parsed = \App\Services\DocumentFilenameParser::parse($doc->file_name);
                                    $docType = $parsed['document_category'] ?? 'Other';
                                }
                                $mainFolder = \App\Services\DocumentFilenameParser::mainFolderForDocumentType($docType);
                                $folderSearchUrl = route('documents.search', array_filter([
                                    'from_sidebar' => 1,
                                    'project_id' => $project->id,
                                    'entity_id' => $project->entity_id,
                                    'main_folder' => $mainFolder,
                                    'document_type' => $docType,
                                ], fn ($v) => $v !== null && $v !== ''));
                            @endphp
                            <tr>
                                <td>
                                    <span style="word-break: break-word;">{{ $doc->file_name }}</span>
                                </td>
                                <td style="color: #334155; font-size: 0.95rem;">
                                    {{ \App\Services\DocumentFilenameParser::folderDisplayLabel($doc->document_type, $doc->file_name, $doc->ocr_text) }}
                                </td>
                                <td class="text-right" style="white-space: nowrap;">
                                    <a href="{{ $folderSearchUrl }}">View</a>
                                    @if(!empty($doc->file_available))
                                        <span style="color: #cbd5e1;"> | </span>
                                        <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank" rel="noopener">Open PDF</a>
                                        &nbsp;|&nbsp;
                                        <a href="{{ route('documents.edit', ['id' => $doc->id]) }}">Replace</a>
                                        <span style="color: #cbd5e1;"> | </span>
                                        <a href="{{ route('documents.download', ['id' => $doc->id]) }}">Download</a>
                                        <span style="color: #cbd5e1;"> | </span>
                                        <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this file?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="padding: 0; background: none; border: none; color: #b91c1c; text-decoration: underline; cursor: pointer;">Delete</button>
                                        </form>
                                    @else
                                        <span style="color: #cbd5e1;"> | </span>
                                        <span style="color:#b91c1c; font-size:0.85rem;">File missing</span>
                                        <span style="color: #cbd5e1;"> | </span>
                                        <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record? File is already missing from storage.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="padding: 0; background: none; border: none; color: #b91c1c; text-decoration: underline; cursor: pointer;">Delete record</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
