@extends('layouts.app')

@section('content')
    <div class="workspace-header">
        <div>
            <p class="workspace-kicker">Company workspace</p>
            <h2>{{ $entity->name }}</h2>
        </div>
        <form method="POST" action="{{ route('workspace.exit') }}">
            @csrf
            <button type="submit" class="workspace-switch-btn">Switch company</button>
        </form>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue"></div>
            <div>
                <div class="stat-value">{{ number_format($totalDocuments) }}</div>
                <div class="stat-label">Documents</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-green"></div>
            <div>
                <div class="stat-value">{{ number_format($totalProjects) }}</div>
                <div class="stat-label">Projects</div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    <div class="card" style="padding:0; overflow:hidden;">
        <div style="background:#212d3e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.05rem;">Latest uploads</h3>
            <a href="{{ entity_route('documents.search') }}" style="color:#fff; font-size:0.9rem;">View all</a>
        </div>
        @if($recentDocuments->isEmpty())
            <p style="margin: 0; padding: 16px;">No documents yet. <a href="{{ entity_route('documents.upload') }}">Upload PDFs</a>.</p>
        @else
            <div class="dms-grid-wrap">
                <table class="dms-grid-table min-w-md">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Project</th>
                            <th>Folder</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($recentDocuments as $doc)
                        <tr>
                            <td>
                                <strong>{{ $doc->file_name }}</strong><br>
                                <span style="font-size: 0.85rem; color: #64748b;">
                                    {{ format_model_datetime($doc, 'created_at') }}
                                </span>
                            </td>
                            <td>{{ $doc->project?->project_number ?? '-' }}</td>
                            <td>{{ $doc->display_folder }}</td>
                            <td class="text-right">
                                @if(!empty($doc->file_available))
                                    <a href="{{ route('documents.download', ['id' => $doc->id]) }}">Download</a>
                                @else
                                    <span style="color:#b91c1c; font-size:0.85rem;">File missing</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($projects->isNotEmpty())
        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-top:0;">Projects in this company</h3>
            <div class="dms-grid-wrap">
                <table class="dms-grid-table min-w-md">
                    <thead>
                        <tr>
                            <th>Project #</th>
                            <th>Name</th>
                            <th>Documents</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                            <tr>
                                <td>{{ $project->project_number }}</td>
                                <td>{{ $project->project_name }}</td>
                                <td>{{ number_format($project->documents_count) }}</td>
                                <td class="text-right">
                                    <a href="{{ entity_route('documents.search', ['project_id' => $project->id]) }}">Documents</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @role('Admin')
                <p style="margin: 12px 0 0;"><a href="{{ entity_route('projects.index') }}">Manage projects</a></p>
            @endrole
        </div>
    @endif

    <style>
        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .workspace-kicker {
            margin: 0 0 4px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .workspace-header h2 { margin: 0; }
        .workspace-switch-btn {
            background: #fff;
            color: var(--navy);
            border: 1px solid var(--border);
        }
        .workspace-switch-btn:hover {
            background: #f8fafc;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 18px;
        }
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .stat-icon-blue { background: #dbeafe; }
        .stat-icon-green { background: #dcfce7; }
        .stat-value {
            font-size: 1.35rem;
            font-weight: 500;
            color: var(--navy);
            line-height: 1.2;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
@endsection
