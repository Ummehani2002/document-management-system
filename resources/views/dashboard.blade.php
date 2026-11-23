@extends('layouts.app')

@section('content')
    <div class="dms-page-header home-greeting">
        <div>
            <p class="dms-page-kicker">Home</p>
            <h2>Good {{ $greetingPeriod }}, {{ $greetingName }}</h2>
            <p class="dms-page-lead">{{ $greetingDate }} — pick a company to open its document workspace.</p>
        </div>
        @role('Admin')
            <div class="dms-page-actions">
                <a href="{{ route('entities.index') }}" class="dms-btn-secondary" style="display:inline-block;padding:9px 14px;text-decoration:none;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#0b1f3a;font-weight:600;font-size:0.85rem;">Manage companies</a>
            </div>
        @endrole
    </div>

    @if (session('info'))
        <div class="success">{{ session('info') }}</div>
    @endif

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalDocuments) }}</div>
                <div class="stat-label">Total documents</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-green" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h7l2 2h9v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalProjects) }}</div>
                <div class="stat-label">Projects</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-navy" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalEntities) }}</div>
                <div class="stat-label">Companies</div>
            </div>
        </div>
    </div>

    <div class="home-companies">
        <div class="home-companies-header">
            <h3>Companies</h3>
            <div class="home-filter-chips" role="tablist" aria-label="Filter by category">
                <button type="button" class="home-chip is-active" data-sector-filter="all" aria-pressed="true">All <span>{{ $totalEntities }}</span></button>
                <button type="button" class="home-chip" data-sector-filter="trading" aria-pressed="false">Trading <span>{{ $tradingCount }}</span></button>
                <button type="button" class="home-chip" data-sector-filter="construction" aria-pressed="false">Construction <span>{{ $constructionCount }}</span></button>
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
            <div class="entity-grid" id="entityGrid">
                @foreach($entityCards as $card)
                    <form method="POST" action="{{ route('entities.enter', $card->id) }}" class="entity-card-form" data-sector="{{ $card->sector }}">
                        @csrf
                        <button type="submit" class="entity-card{{ !empty($card->logo_url) ? ' has-logo' : '' }}">
                            <div class="entity-card-banner{{ !empty($card->logo_url) ? ' entity-card-banner-logo' : '' }}">
                                @if(!empty($card->logo_url))
                                    <img class="entity-card-banner-image" src="{{ $card->logo_url }}" alt="{{ $card->name }}">
                                @else
                                    <span class="entity-card-category">{{ $card->sector_label }}</span>
                                    <span class="entity-card-avatar">{{ $card->initials }}</span>
                                @endif
                            </div>
                            <div class="entity-card-body">
                                <h3 class="entity-card-name" title="{{ $card->name }}">{{ $card->name }}</h3>
                                <p class="entity-card-sector">{{ $card->sector_label }}</p>
                            </div>
                            <div class="entity-card-footer">
                                @if($card->documents_count > 0)
                                    <span class="entity-card-docs">{{ number_format($card->documents_count) }} {{ Str::plural('doc', $card->documents_count) }}</span>
                                    <span class="entity-card-arrow" aria-hidden="true">→</span>
                                @else
                                    <span class="entity-card-docs entity-card-empty">Upload first file</span>
                                    <span class="entity-card-arrow" aria-hidden="true">→</span>
                                @endif
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>
            <p class="home-filter-empty" id="entityFilterEmpty" hidden>No companies in this category.</p>
        @endif
    </div>

    <style>
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon svg { width: 20px; height: 20px; }
        .stat-icon-blue { background: #dbeafe; color: #1d4ed8; }
        .stat-icon-green { background: #dcfce7; color: #15803d; }
        .stat-icon-navy { background: #e2e8f0; color: #0b1f3a; }
        .stat-value {
            font-size: 1.35rem;
            font-weight: 600;
            color: #0b1f3a;
            line-height: 1.2;
        }
        .stat-label { color: #64748b; font-size: 0.85rem; }
        .home-companies-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .home-companies-header h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 600;
            color: #0b1f3a;
        }
        .home-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .home-chip {
            background: #fff !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 999px !important;
            padding: 7px 12px !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            cursor: pointer;
        }
        .home-chip span {
            margin-left: 4px;
            color: #64748b;
            font-weight: 500;
        }
        .home-chip.is-active {
            background: #0b1f3a !important;
            border-color: #0b1f3a !important;
            color: #fff !important;
        }
        .home-chip.is-active span { color: #cbd5e1; }
        .home-filter-empty {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .entity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 12px;
            align-items: stretch;
        }
        .entity-card-form {
            margin: 0;
            min-width: 0;
            height: 100%;
            display: flex;
        }
        .entity-card-form[hidden] { display: none !important; }
        button.entity-card,
        .entity-card {
            width: 100%;
            height: 100%;
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
        button.entity-card:hover {
            border-color: #3b82f6 !important;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.12);
        }
        .entity-card-banner {
            background: #f1f5f9;
            color: #0b1f3a;
            padding: 14px 16px 36px;
            position: relative;
            min-height: 56px;
            border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0;
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
            max-height: 140px;
            object-fit: contain;
            object-position: center;
            display: block;
            padding: 12px 16px;
            box-sizing: border-box;
            background: #fff;
        }
        .entity-card.has-logo .entity-card-body { padding-top: 14px; }
        .entity-card-category {
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            color: #64748b;
            text-transform: uppercase;
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
        }
        .entity-card-body {
            padding: 14px 16px 8px;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            min-height: 0;
        }
        .entity-card-name {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #0b1f3a !important;
            line-height: 1.35;
            min-height: calc(1.35em * 2);
            max-height: calc(1.35em * 2);
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            word-break: break-word;
        }
        .entity-card-sector {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 0.78rem;
        }
        .entity-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px 14px;
            background: #ffffff;
            flex-shrink: 0;
            margin-top: auto;
        }
        .entity-card-docs { color: #64748b !important; font-size: 0.85rem; }
        .entity-card-empty { color: #1d4ed8 !important; font-weight: 600; }
        .entity-card-arrow { color: #1d4ed8; font-size: 1rem; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var chips = document.querySelectorAll('[data-sector-filter]');
            var cards = document.querySelectorAll('#entityGrid .entity-card-form');
            var empty = document.getElementById('entityFilterEmpty');
            if (!chips.length || !cards.length) return;

            function applyFilter(sector) {
                var visible = 0;
                cards.forEach(function (card) {
                    var match = sector === 'all' || card.getAttribute('data-sector') === sector;
                    card.hidden = !match;
                    if (match) visible++;
                });
                if (empty) empty.hidden = visible > 0;
                chips.forEach(function (chip) {
                    var active = chip.getAttribute('data-sector-filter') === sector;
                    chip.classList.toggle('is-active', active);
                    chip.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }

            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    applyFilter(chip.getAttribute('data-sector-filter') || 'all');
                });
            });
        });
    </script>
@endsection
