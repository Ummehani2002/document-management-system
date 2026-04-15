@extends('layouts.app')

@section('content')
    <h2>Project Dashboard</h2>

    <form method="get" action="{{ route('project-dashboard') }}" class="card" style="margin-bottom: 20px;">
        <label for="project_number" style="display: block; margin-bottom: 8px; font-weight: 600;">Project number</label>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <input
                type="text"
                name="project_number"
                id="project_number"
                value="{{ $projectNumberQuery }}"
                placeholder="e.g. MH-0026"
                style="max-width: 280px; margin: 0;"
                autocomplete="off"
            >
            <button type="submit">Show PDFs</button>
        </div>
    </form>

    @if($projectNumberQuery !== '' && !$project)
        <div class="card" style="border-color: #e2e8f0;">
            <p style="margin: 0;">No project found with number <strong>{{ $projectNumberQuery }}</strong>. Check Project Master for the exact project number.</p>
        </div>
    @elseif($project)
        <p style="color: #64748b; margin-top: 0;">
            <strong>{{ $project->project_name }}</strong>
            @if($project->entity)
                — {{ $project->entity->name }}
            @endif
        </p>

        @if($documents->isEmpty())
            <div class="card">
                <p style="margin: 0;">No PDFs uploaded for this project yet.</p>
            </div>
        @else
            <div class="card" style="padding: 0; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 640px;">
                    <thead>
                        <tr style="background: #212d3e; color: #fff;">
                            <th style="text-align: left; padding: 10px 12px;">PDF</th>
                            <th style="text-align: left; padding: 10px 12px;">Folder</th>
                            <th style="text-align: right; padding: 10px 12px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $doc)
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 12px;">
                                    <strong style="word-break: break-word;">{{ $doc->file_name }}</strong>
                                </td>
                                <td style="padding: 10px 12px; color: #334155; font-size: 0.95rem;">
                                    {{ \App\Services\DocumentFilenameParser::folderDisplayLabel($doc->document_type, $doc->file_name) }}
                                </td>
                                <td style="padding: 10px 12px; text-align: right; white-space: nowrap;">
                                    <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank" rel="noopener">View</a>
                                    &nbsp;|&nbsp;
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
