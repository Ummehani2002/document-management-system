@extends('layouts.app')

@section('content')
    <div class="home-hero">
        <div class="home-hero-copy">
            <h2>Welcome to Document Management System</h2>
        </div>
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

    @if($selectedSector === null)
        <div class="home-step">
            <div class="home-step-header">
                <span class="home-step-num">1</span>
                <div>
                    <h3>Select Category</h3>
                </div>
            </div>

            <div class="sector-grid">
                <form method="POST" action="{{ route('dashboard.sector') }}" class="sector-form">
                    @csrf
                    <input type="hidden" name="sector" value="trading">
                    <button type="submit" class="sector-card sector-trading">
                        <span class="sector-icon" aria-hidden="true">
                            <svg viewBox="0 0 48 48" fill="none"><path d="M6 34h36M10 34V22l8-4 6 6 8-8 6 4v14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 22h4M28 18h4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        </span>
                        <span class="sector-title">Trading</span>
                        <span class="sector-meta">{{ $tradingCount }} {{ Str::plural('company', $tradingCount) }}</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('dashboard.sector') }}" class="sector-form">
                    @csrf
                    <input type="hidden" name="sector" value="construction">
                    <button type="submit" class="sector-card sector-construction">
                        <span class="sector-icon" aria-hidden="true">
                            <svg viewBox="0 0 48 48" fill="none"><path d="M16 38V22l8-6 8 6v16M12 38h24M20 28h8M22 22v6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span class="sector-title">Construction</span>
                        <span class="sector-meta">{{ $constructionCount }} {{ Str::plural('company', $constructionCount) }}</span>
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="home-step">
            <div class="home-step-header">
                <span class="home-step-num">2</span>
                <div>
                    <h3>Select Company</h3>
                    <p>Choose the {{ $selectedSectorLabel }} company you want to manage documents for.</p>
                </div>
                <form method="POST" action="{{ route('dashboard.sector.clear') }}" class="home-change-type">
                    @csrf
                    <button type="submit" class="home-change-type-btn">Change category</button>
                </form>
                @role('Admin')
                    <a href="{{ route('entities.index') }}" class="home-manage-link">Manage companies</a>
                @endrole
            </div>

            <div class="home-sector-chip">{{ $selectedSectorLabel }}</div>

            @if($entityCards->isEmpty())
                <div class="card">
                    <p style="margin:0;">
                        @if($isAdmin)
                            No {{ strtolower($selectedSectorLabel) }} companies yet. <a href="{{ route('entities.create') }}">Add a company</a> to get started.
                        @else
                            You are not assigned to any {{ strtolower($selectedSectorLabel) }} company yet. Contact an administrator for access.
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
                                    <span class="entity-card-arrow" aria-hidden="true">→</span>
                                </div>
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <style>
        .home-hero {
            background: linear-gradient(120deg, #0b1f3a 0%, #163a66 55%, #1e4d7b 100%);
            color: #fff;
            border-radius: 12px;
            padding: 28px 32px;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(11, 31, 58, 0.18);
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }
        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 18px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
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
            font-weight: 600;
            color: #0b1f3a;
            line-height: 1.2;
        }
        .stat-label {
            color: #64748b;
            font-size: 0.85rem;
        }
        .home-hero-copy h2 {
            margin: 0;
            font-size: 1.45rem;
            font-weight: 600;
            color: #fff;
        }
        .home-step {
            margin-bottom: 28px;
        }
        .home-step-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .home-step-header h3 {
            margin: 0 0 4px;
            font-size: 1.15rem;
            font-weight: 600;
            color: #0b1f3a;
        }
        .home-step-header p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .home-step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1d4ed8;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .home-change-type {
            margin-left: auto;
        }
        .home-change-type-btn {
            background: #fff !important;
            color: #1d4ed8 !important;
            border: 1px solid #bfdbfe !important;
            border-radius: 8px;
            padding: 8px 12px !important;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .home-change-type-btn:hover {
            background: #eff6ff !important;
        }
        .home-manage-link {
            font-size: 0.9rem;
            color: #1d4ed8;
        }
        .home-sector-chip {
            display: inline-block;
            margin-bottom: 16px;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .sector-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }
        .sector-form { margin: 0; }
        button.sector-card,
        .sector-card {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            text-align: left;
            padding: 22px 24px !important;
            border-radius: 12px;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            font: inherit;
        }
        .sector-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }
        .sector-trading:hover { border-color: #3b82f6 !important; }
        .sector-construction:hover { border-color: #16a34a !important; }
        .sector-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sector-icon svg { width: 28px; height: 28px; }
        .sector-trading .sector-icon {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .sector-construction .sector-icon {
            background: #dcfce7;
            color: #15803d;
        }
        .sector-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0b1f3a !important;
        }
        .sector-trading .sector-title { color: #1d4ed8 !important; }
        .sector-construction .sector-title { color: #15803d !important; }
        .sector-meta {
            color: #64748b !important;
            font-size: 0.85rem;
        }
        .entity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 12px;
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
            color: #0b1f3a !important;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }
        button.entity-card:hover,
        .entity-card:hover {
            background: #ffffff !important;
            border-color: #3b82f6 !important;
            color: #0b1f3a !important;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.12);
        }
        button.entity-card:focus-visible,
        .entity-card:focus-visible {
            outline: 2px solid #1d4ed8;
            outline-offset: 2px;
            background: #ffffff !important;
            color: #0b1f3a !important;
        }
        .entity-card-banner {
            background: #f1f5f9;
            color: #0b1f3a;
            padding: 14px 16px 36px;
            position: relative;
            min-height: 56px;
            border-bottom: 1px solid #e2e8f0;
        }
        .entity-card-banner-logo {
            padding: 0;
            min-height: 140px;
            height: 140px;
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
            background: #1d4ed8;
            color: #fff;
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
            font-size: 1.05rem;
            font-weight: 600;
            color: #0b1f3a !important;
        }
        .entity-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px 14px;
            background: #ffffff;
        }
        .entity-card-docs {
            color: #64748b !important;
            font-size: 0.85rem;
        }
        .entity-card-arrow {
            color: #1d4ed8;
            font-size: 1rem;
        }
    </style>
@endsection
