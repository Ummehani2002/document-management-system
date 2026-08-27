@extends('layouts.app')

@section('content')
    <div class="home-header">
        <h2>Select company</h2>
        <span class="home-badge">{{ $totalEntities }} {{ Str::plural('company', $totalEntities) }}</span>
        @role('Admin')
            <a href="{{ route('entities.index') }}" class="home-manage-link">Manage companies</a>
        @endrole
    </div>

    @if (session('info'))
        <div class="success">{{ session('info') }}</div>
    @endif

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue"></div>
            <div>
                <div class="stat-value">{{ number_format($totalDocuments) }}</div>
                <div class="stat-label">Total documents</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-green"></div>
            <div>
                <div class="stat-value">{{ number_format($totalProjects) }}</div>
                <div class="stat-label">Projects</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-purple"></div>
            <div>
                <div class="stat-value">{{ number_format($totalEntities) }}</div>
                <div class="stat-label">Companies</div>
            </div>
        </div>
    </div>

    @if($entityCards->isEmpty())
        <div class="card">
            <p style="margin:0;">
                @if($isAdmin)
                    No companies yet. <a href="{{ route('entities.create') }}">Add a company</a> to get started.
                @else
                    You are not assigned to any company yet. Contact an administrator for access.
                @endif
            </p>
        </div>
    @else
        <div class="entity-grid">
            @foreach($entityCards as $card)
                <article class="entity-card">
                    <div class="entity-card-banner">
                        <span class="entity-card-category">Company</span>
                        <span class="entity-card-count">{{ number_format($card->documents_count) }}</span>
                        <span class="entity-card-avatar">{{ $card->initials }}</span>
                    </div>
                    <div class="entity-card-body">
                        <h3 class="entity-card-name">{{ $card->name }}</h3>
                        <p class="entity-card-desc">
                            Documents, projects, and files for {{ $card->name }}.
                        </p>
                        <div class="entity-card-tags">
                            <span class="entity-tag">Documents</span>
                            <span class="entity-tag">Projects</span>
                            @if($card->projects_count > 0)
                                <span class="entity-tag">{{ $card->projects_count }} projects</span>
                            @endif
                        </div>
                        @if($card->recent_documents->isNotEmpty())
                            <div class="entity-recent-uploads">
                                <p class="entity-recent-title">Latest uploads</p>
                                <ul class="entity-recent-list">
                                    @foreach($card->recent_documents as $doc)
                                        <li>
                                            <span class="entity-recent-file" title="{{ $doc->file_name }}">{{ $doc->file_name }}</span>
                                            <span class="entity-recent-date">{{ format_model_datetime($doc, 'created_at', 'd M Y') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <div class="entity-card-footer">
                        <span class="entity-card-docs">{{ number_format($card->documents_count) }} docs</span>
                        <form method="POST" action="{{ route('entities.enter', $card->id) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="entity-open-btn">Open</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <style>
        .home-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .home-header h2 { margin: 0; }
        .home-badge {
            background: #e2e8f0;
            color: #475569;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
        }
        .home-manage-link {
            margin-left: auto;
            font-size: 0.9rem;
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
        .stat-icon-purple { background: #ede9fe; }
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
        .entity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .entity-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            display: flex;
            flex-direction: column;
        }
        .entity-card-banner {
            background: var(--navy);
            color: #fff;
            padding: 14px 16px 36px;
            position: relative;
            min-height: 56px;
        }
        .entity-card-category {
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            opacity: 0.85;
        }
        .entity-card-count {
            position: absolute;
            top: 14px;
            right: 16px;
            font-size: 0.9rem;
            opacity: 0.95;
        }
        .entity-card-avatar {
            position: absolute;
            left: 16px;
            bottom: -18px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gold);
            color: var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            border: 3px solid #fff;
        }
        .entity-card-body {
            padding: 28px 16px 12px;
            flex: 1;
        }
        .entity-card-name {
            margin: 0 0 8px;
            font-size: 1.05rem;
            color: var(--navy);
        }
        .entity-card-desc {
            margin: 0 0 12px;
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.45;
        }
        .entity-card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .entity-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.78rem;
        }
        .entity-recent-uploads {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }
        .entity-recent-title {
            margin: 0 0 8px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
        }
        .entity-recent-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .entity-recent-list li {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 5px 0;
            font-size: 0.82rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .entity-recent-list li:last-child {
            border-bottom: none;
        }
        .entity-recent-file {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text);
        }
        .entity-recent-date {
            flex-shrink: 0;
            color: var(--text-muted);
            font-size: 0.78rem;
        }
        .entity-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
        }
        .entity-card-docs {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .entity-open-btn {
            background: transparent;
            color: var(--gold-dark);
            border: none;
            padding: 0;
            font: inherit;
            cursor: pointer;
            text-decoration: underline;
        }
        .entity-open-btn:hover {
            color: var(--gold);
            background: transparent;
        }
    </style>
@endsection
