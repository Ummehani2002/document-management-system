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
                <form method="POST" action="{{ route('entities.enter', $card->id) }}" class="entity-card-form">
                    @csrf
                    <button type="submit" class="entity-card{{ !empty($card->logo_url) ? ' has-logo' : '' }}">
                        <div class="entity-card-banner{{ !empty($card->logo_url) ? ' entity-card-banner-logo' : '' }}">
                            @if(!empty($card->logo_url))
                                <img class="entity-card-banner-image" src="{{ $card->logo_url }}" alt="{{ $card->name }}">
                                <span class="entity-card-count">{{ number_format($card->documents_count) }}</span>
                            @else
                                <span class="entity-card-category">Company</span>
                                <span class="entity-card-count">{{ number_format($card->documents_count) }}</span>
                                <span class="entity-card-avatar">{{ $card->initials }}</span>
                            @endif
                        </div>
                        <div class="entity-card-body">
                            <h3 class="entity-card-name">{{ $card->name }}</h3>
                        </div>
                        <div class="entity-card-footer">
                            <span class="entity-card-docs">{{ number_format($card->documents_count) }} docs</span>
                        </div>
                    </button>
                </form>
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
        .entity-card-form {
            margin: 0;
            min-width: 0;
        }
        button.entity-card,
        .entity-card {
            width: 100%;
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff !important;
            display: flex;
            flex-direction: column;
            text-align: left;
            padding: 0 !important;
            font: inherit;
            color: #1e293b !important;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }
        button.entity-card:hover,
        .entity-card:hover {
            background: #ffffff !important;
            border-color: #c5a059 !important;
            color: #1e293b !important;
            box-shadow: 0 4px 14px rgba(33, 45, 62, 0.08);
        }
        button.entity-card:focus-visible,
        .entity-card:focus-visible {
            outline: 2px solid #c5a059;
            outline-offset: 2px;
            background: #ffffff !important;
            color: #1e293b !important;
        }
        .entity-card-banner {
            background: #f8fafc;
            color: #1e293b;
            padding: 14px 16px 36px;
            position: relative;
            min-height: 56px;
            border-bottom: 1px solid #e2e8f0;
        }
        .entity-card-banner-logo {
            padding: 0;
            min-height: 160px;
            height: 160px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .entity-card-banner-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            padding: 8px 12px;
            box-sizing: border-box;
            background: #fff;
        }
        .entity-card.has-logo .entity-card-body {
            padding-top: 16px;
        }
        .entity-card-category {
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            color: #64748b;
            text-transform: uppercase;
        }
        .entity-card-count {
            position: absolute;
            top: 10px;
            right: 12px;
            font-size: 0.85rem;
            color: #64748b;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 2px 8px;
            z-index: 1;
        }
        .entity-card-avatar {
            position: absolute;
            left: 16px;
            bottom: -18px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #c5a059;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            border: 3px solid #fff;
            overflow: hidden;
        }
        .entity-card-body {
            padding: 16px 16px 8px;
            flex: 1;
            background: #ffffff;
        }
        .entity-card-name {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f172a !important;
        }
        .entity-card-footer {
            display: flex;
            align-items: center;
            padding: 8px 16px 14px;
            background: #ffffff;
        }
        .entity-card-docs {
            color: #64748b !important;
            font-size: 0.85rem;
        }
    </style>
@endsection
