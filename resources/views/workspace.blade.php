@extends('layouts.app')

@section('content')
    <nav class="dms-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}">Home</a>
        <span aria-hidden="true">›</span>
        <span>{{ $entitySectorLabel }}</span>
        <span aria-hidden="true">›</span>
        <span class="is-current">{{ $entity->name }}</span>
    </nav>

    <div class="dms-page-header workspace-header">
        <div>
            <p class="dms-page-kicker">Company workspace</p>
            <h2>{{ $entity->name }}</h2>
            <p class="dms-page-lead">Overview of documents and recent activity for this company.</p>
        </div>
        <div class="dms-page-actions">
            <a href="{{ entity_route('documents.upload') }}" class="dms-upload-cta" style="display:inline-block;padding:10px 16px;background:#0b1f3a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:0.85rem;">Upload</a>
        </div>
    </div>

    <div class="stats-row">
        <a class="stat-card stat-card-link" href="{{ entity_route('documents.search') }}">
            <div class="stat-icon stat-icon-blue" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalDocuments) }}</div>
                <div class="stat-label">Documents</div>
            </div>
        </a>
        <a class="stat-card stat-card-link" href="{{ entity_route('documents.search') }}">
            <div class="stat-icon stat-icon-green" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h7l2 2h9v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalProjects) }}</div>
                <div class="stat-label">Projects</div>
            </div>
        </a>
        <a class="stat-card stat-card-link" href="{{ entity_route('documents.search') }}">
            <div class="stat-icon stat-icon-amber" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/><circle cx="12" cy="12" r="9"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ number_format($uploadedThisWeek) }}</div>
                <div class="stat-label">Uploaded this week</div>
            </div>
        </a>
    </div>

    @if (session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    <div class="card" style="padding:0; overflow:hidden;">
        <div style="background:#f8fafc; color:#1e293b; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0;">
            <h3 style="margin:0; font-size:1.05rem;">Latest uploads</h3>
            <a href="{{ entity_route('documents.search') }}" style="color:#1d4ed8; font-size:0.9rem;">View all</a>
        </div>
        @if($recentDocuments->isEmpty())
            <p style="margin: 0; padding: 16px;">No documents yet. <a href="{{ entity_route('documents.upload') }}">Upload first file</a>.</p>
        @else
            <div class="dms-grid-wrap">
                <table class="dms-grid-table min-w-md">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Project</th>
                            <th>Folder</th>
                            <th>Uploaded by</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($recentDocuments as $doc)
                        @php
                            $ext = strtolower(pathinfo((string) $doc->file_name, PATHINFO_EXTENSION) ?: 'pdf');
                        @endphp
                        <tr>
                            <td>
                                <div class="dms-file-cell">
                                    <span class="dms-file-icon dms-file-icon-{{ $ext === 'pdf' ? 'pdf' : 'file' }}" aria-hidden="true">{{ strtoupper($ext === 'pdf' ? 'PDF' : substr($ext, 0, 3)) }}</span>
                                    <span>
                                        <strong>{{ $doc->file_name }}</strong><br>
                                        <span style="font-size: 0.85rem; color: #64748b;">
                                            {{ format_model_datetime($doc, 'created_at') }}
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td>{{ $doc->project?->project_number ?? '-' }}</td>
                            <td>{{ $doc->display_folder }}</td>
                            <td>{{ $doc->modifiedBy?->name ?: '—' }}</td>
                            <td class="text-right" style="white-space:nowrap;">
                                @if(!empty($doc->file_available))
                                    <a href="{{ route('documents.view', ['id' => $doc->id]) }}" target="_blank" rel="noopener">Preview</a>
                                    <span style="color:#cbd5e1;"> · </span>
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
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        a.stat-card-link,
        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 18px;
            text-decoration: none !important;
            color: inherit !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        a.stat-card-link:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.1);
        }
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon svg { width: 20px; height: 20px; }
        .stat-icon-blue { background: #dbeafe; color: #1d4ed8; }
        .stat-icon-green { background: #dcfce7; color: #15803d; }
        .stat-icon-amber { background: #ffedd5; color: #c2410c; }
        .stat-value {
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--navy);
            line-height: 1.2;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .dms-file-cell {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .dms-file-icon {
            flex: 0 0 auto;
            min-width: 34px;
            height: 34px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .dms-file-icon-pdf { background: #fee2e2; color: #b91c1c; }
        .dms-file-icon-file { background: #e2e8f0; color: #334155; }
    </style>
@endsection
