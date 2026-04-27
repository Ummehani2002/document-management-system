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
        <div class="card" style="margin-bottom: 20px;">
            <p style="margin: 0 0 12px; color: #334155;">Several projects match <strong>{{ $searchQuery }}</strong>. Pick one:</p>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 520px;">
                    <thead>
                        <tr style="background: #212d3e; color: #fff;">
                            <th style="text-align: left; padding: 10px 12px;">Project number</th>
                            <th style="text-align: left; padding: 10px 12px;">Project name</th>
                            <th style="text-align: left; padding: 10px 12px;">Entity</th>
                            <th style="text-align: right; padding: 10px 12px;">PDFs</th>
                            <th style="text-align: right; padding: 10px 12px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $p)
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 12px;">{{ $p->project_number }}</td>
                                <td style="padding: 10px 12px;">{{ $p->project_name }}</td>
                                <td style="padding: 10px 12px;">{{ $p->entity?->name ?? '—' }}</td>
                                <td style="padding: 10px 12px; text-align: right;">{{ (int) ($p->documents_count ?? 0) }}</td>
                                <td style="padding: 10px 12px; text-align: right;">
                                    <a href="{{ route('project-dashboard', ['project_id' => $p->id]) }}">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
            <div class="card" style="padding: 0; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 720px;">
                    <thead>
                        <tr style="background: #212d3e; color: #fff;">
                            <th style="text-align: left; padding: 10px 12px;">PDF</th>
                            <th style="text-align: left; padding: 10px 12px;">Category / folder</th>
                            <th style="text-align: right; padding: 10px 12px;">Actions</th>
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
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 12px;">
                                    <span style="word-break: break-word;">{{ $doc->file_name }}</span>
                                </td>
                                <td style="padding: 10px 12px; color: #334155; font-size: 0.95rem;">
                                    {{ \App\Services\DocumentFilenameParser::folderDisplayLabel($doc->document_type, $doc->file_name) }}
                                </td>
                                <td style="padding: 10px 12px; text-align: right; white-space: nowrap;">
                                    <a href="{{ $folderSearchUrl }}">View</a>
                                    <span style="color: #cbd5e1;"> | </span>
                                    <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank" rel="noopener">Open PDF</a>
                                    <span style="color: #cbd5e1;"> | </span>
                                    <a href="{{ route('documents.download', ['id' => $doc->id]) }}">Download</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
