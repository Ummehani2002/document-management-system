<!DOCTYPE html>
<html>
<head>
    <title>Document Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy: #0c1829;
            --navy-hover: #152238;
            --navy-soft: #1e2d42;
            --gold: #c5a059;
            --gold-dark: #a88962;
            --green: #238651;
            --green-soft: #e8f4ec;
            --green-text: #1a5c38;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --sidebar-text: #e2e8f0;
            --sidebar-muted: #94a3b8;
            --header-top-h: 58px;
            --header-nav-h: 52px;
        }

        html, body {
            overflow-x: hidden;
            max-width: 100%;
        }

        body {
            font-family: "Montserrat", "Segoe UI", system-ui, -apple-system, sans-serif;
            background: var(--bg-page);
            margin: 0;
            color: var(--text);
            font-size: 13px;
            font-weight: 400;
            line-height: 1.45;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 500;
            line-height: 1.3;
        }

        h1 { font-size: 1.3rem; }
        h2 { font-size: 1.1rem; }
        h3 { font-size: 1rem; }
        h4 { font-size: 0.95rem; }

        strong, b, th, label, button {
            font-weight: 400;
        }

        .main-content a {
            color: var(--gold-dark);
        }

        .main-content a:hover {
            color: var(--gold);
        }

        .dms-header {
            background: var(--navy);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.18);
        }

        .dms-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: var(--header-top-h);
            padding: 0 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .dms-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .dms-brand-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .dms-brand-text {
            min-width: 0;
        }

        .dms-brand-title {
            display: block;
            font-size: 1.02rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dms-brand-subtitle {
            display: block;
            font-size: 0.98rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            line-height: 1.25;
            opacity: 0.98;
        }

        .dms-topbar-right {
            display: flex;
            align-items: center;
            gap: 0;
            flex-shrink: 0;
        }

        .dms-date-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.78rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .dms-date-pill svg {
            width: 14px;
            height: 14px;
            color: var(--gold);
            flex-shrink: 0;
        }

        .dms-topbar-divider {
            width: 1px;
            height: 28px;
            background: rgba(255, 255, 255, 0.18);
            margin: 0 16px;
        }

        .dms-user-block {
            position: relative;
        }

        .dms-user-dropdown {
            position: relative;
        }

        .dms-user-dropdown > summary {
            list-style: none;
            cursor: pointer;
        }

        .dms-user-dropdown > summary::-webkit-details-marker {
            display: none;
        }

        .dms-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--green);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: border-color 0.15s ease;
        }

        .dms-user-dropdown[open] .dms-user-avatar,
        .dms-user-dropdown > summary:hover .dms-user-avatar {
            border-color: var(--gold);
        }

        .dms-user-dropdown-panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 120px;
            padding: 8px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
            z-index: 120;
        }

        .dms-logout-btn {
            background: transparent;
            border: none;
            color: var(--navy);
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            padding: 8px 10px;
            width: 100%;
            text-align: left;
            border-radius: 6px;
        }

        .dms-logout-btn:hover {
            background: #f8fafc;
            color: var(--gold-dark);
        }

        .dms-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 4px 0;
            min-height: var(--header-nav-h);
            padding: 0 20px;
            background:
                linear-gradient(rgba(12, 24, 41, 0.88), rgba(12, 24, 41, 0.92)),
                linear-gradient(135deg, #1a2f4a 0%, #0c1829 50%, #162536 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .dms-nav-link {
            display: inline-block;
            padding: 14px 18px 12px;
            color: #fff;
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border-bottom: 3px solid transparent;
            transition: color 0.15s, border-color 0.15s;
            white-space: nowrap;
        }

        .dms-nav-link:hover {
            color: var(--gold);
        }

        .dms-nav-link.is-active {
            border-bottom-color: var(--gold);
            color: #fff;
        }

        .dms-entity-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(197, 160, 89, 0.18);
            border: 1px solid rgba(197, 160, 89, 0.35);
            color: var(--gold);
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }

        @media (max-width: 1100px) {
            .dms-topbar {
                padding: 8px 16px;
                flex-wrap: wrap;
            }

            .dms-brand-title,
            .dms-brand-subtitle {
                white-space: normal;
            }

            .dms-date-pill {
                display: none;
            }

            .dms-nav {
                justify-content: flex-start;
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 2px;
            }

            .dms-nav-link {
                padding: 12px 14px 10px;
                font-size: 0.68rem;
            }
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            background: var(--bg-page);
            min-width: 0;
            padding: 0 32px 40px 24px;
            box-sizing: border-box;
        }

        .container {
            max-width: min(100%, 1480px);
            width: 100%;
            margin: 32px auto 40px;
            background: var(--bg-card);
            padding: 32px 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 16px rgba(33, 45, 62, 0.06);
        }

        h2 {
            margin-top: 0;
            color: var(--navy);
        }

        input[type="file"], input[type="text"], select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            max-width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-card);
        }

        select { margin-top: 4px; }

        label { display: block; font-weight: 600; margin-bottom: 4px; }

        .btn-primary, button[type="submit"] {
            background: var(--navy);
        }

        button {
            background: var(--navy);
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover, .btn-primary:hover {
            background: var(--navy-hover);
        }

        .success {
            background: var(--green-soft);
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            color: var(--green-text);
            border: 1px solid rgba(35, 134, 81, 0.2);
        }

        .card {
            border: 1px solid var(--border);
            background: var(--bg-card);
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
        }

        .layout-row {
            display: flex;
            min-height: calc(100vh - var(--header-top-h) - var(--header-nav-h));
        }

        .sidebar {
            width: 215px;
            min-width: 215px;
            background: var(--navy);
            border-right: 1px solid rgba(0, 0, 0, 0.15);
            padding: 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-shell {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }

        .sidebar .folder-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sidebar .folder-item {
            margin-bottom: 12px;
        }

        .sidebar .folder-toggle {
            width: 100%;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            color: var(--sidebar-text);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 12.5px;
            font-weight: 400;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar .folder-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .folder-toggle .caret {
            font-size: 0.8rem;
            color: var(--sidebar-muted);
        }

        .sidebar .folder-toggle.active {
            background: var(--navy-hover);
            border-color: rgba(197, 160, 89, 0.35);
        }

        .folder-blocks-main {
            max-width: min(100%, 1480px);
            width: 100%;
            margin: 32px auto 40px;
        }

        .folder-blocks-main[hidden] {
            display: none !important;
        }

        .folder-blocks-main-inner {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(33, 45, 62, 0.06);
            padding: 28px 32px 32px;
        }

        .folder-blocks-main-heading {
            margin: 0 0 20px 0;
            font-size: 1.35rem;
            color: var(--navy);
        }

        .folder-blocks-main-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
            gap: 14px;
            margin: 0;
            padding: 0;
        }

        .folder-blocks-main .folder-block-card {
            margin: 0;
            min-width: 0;
        }

        .folder-blocks-main .folder-block-card a {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            min-height: 88px;
            padding: 14px 14px 16px;
            background: #f8fafc;
            border: 1.5px solid #94a3b8;
            border-radius: 10px;
            color: var(--navy) !important;
            text-decoration: none !important;
            font-size: 0.82rem;
            font-weight: 600;
            line-height: 1.3;
            box-shadow: 0 1px 3px rgba(33, 45, 62, 0.06);
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s ease;
        }

        .folder-blocks-main .folder-block-card a:hover {
            border-color: rgba(196, 164, 124, 0.85);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14);
            transform: scale(1.06);
            color: var(--navy) !important;
        }

        .folder-blocks-main .folder-block-card a.is-active {
            border-color: var(--gold);
            background: #fff;
            box-shadow: 0 0 0 2px rgba(196, 164, 124, 0.35);
        }

        .folder-blocks-main .folder-block-icon {
            font-size: 1.25rem;
            line-height: 1;
            opacity: 0.9;
        }

        .folder-blocks-main .folder-block-title {
            display: block;
            word-break: break-word;
        }

        .folder-blocks-main-empty {
            grid-column: 1 / -1;
            color: var(--text-muted);
            font-size: 0.95rem;
            padding: 12px 4px;
        }

        /* Pagination (Bootstrap 5 markup from Paginator::useBootstrapFive) */
        .pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            list-style: none;
            padding: 0;
            margin: 20px 0;
            align-items: center;
        }
        .pagination .page-item .page-link {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text);
            text-decoration: none;
        }
        .pagination .page-item .page-link:hover {
            background: #f1f5f9;
        }
        .pagination .page-item.active .page-link {
            background: var(--navy);
            color: #fff;
            border-color: var(--navy);
        }
        .pagination .page-item.disabled .page-link {
            opacity: 0.45;
            pointer-events: none;
        }

        .dms-grid-wrap {
            padding: 0;
            overflow-x: auto;
        }

        .dms-grid-table {
            width: 100%;
            border-collapse: collapse;
        }

        .dms-grid-table th,
        .dms-grid-table td {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }

        .dms-grid-table thead th {
            background: var(--navy);
            color: #fff;
            border-color: #2d3a52;
        }

        .dms-grid-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .dms-grid-table .text-right {
            text-align: right;
        }

        .dms-grid-table .text-center {
            text-align: center;
        }

        .dms-grid-table.min-w-sm { min-width: 520px; }
        .dms-grid-table.min-w-md { min-width: 720px; }
        .dms-grid-table.min-w-lg { min-width: 1100px; }
        .dms-grid-table.min-w-xl { min-width: 2200px; }

        @media (max-width: 1200px) {
            .sidebar {
                width: 190px;
                min-width: 190px;
            }
        }
    </style>
</head>
<body>

@php
    $userInitials = auth()->user()->name
        ? entity_initials(auth()->user()->name)
        : strtoupper(substr(auth()->user()->email, 0, 2));
    $displayDate = now()->timezone(config('app.timezone', 'Asia/Dubai'))->format('l, j F Y');
    $navActive = fn (array $routes): string => request()->routeIs($routes) ? ' is-active' : '';
@endphp

<header class="dms-header">
    <div class="dms-topbar">
        <div class="dms-brand">
            <img
                class="dms-brand-logo"
                src="{{ asset('images/tanseeq-white.png') }}?v=2"
                alt="Tanseeq Investment"
            />
            <div class="dms-brand-text">
                <span class="dms-brand-title">Tanseeq Investment</span>
                <span class="dms-brand-subtitle">Document Management System</span>
            </div>
            @if(!empty($currentEntity))
                <span class="dms-entity-badge" title="{{ $currentEntity->name }}">{{ $currentEntity->name }}</span>
            @endif
        </div>
        <div class="dms-topbar-right">
            <div class="dms-date-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                    <path d="M16 2v4M8 2v4M3 10h18"></path>
                </svg>
                <span>{{ $displayDate }}</span>
            </div>
            <span class="dms-topbar-divider" aria-hidden="true"></span>
            <div class="dms-user-block">
                <details class="dms-user-dropdown">
                    <summary class="dms-user-avatar" title="{{ auth()->user()->name ?: auth()->user()->email }}" aria-label="Account menu">{{ $userInitials }}</summary>
                    <div class="dms-user-dropdown-panel">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dms-logout-btn">Logout</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </div>
    <nav class="dms-nav" aria-label="Main navigation">
        <a href="{{ route('dashboard') }}" class="dms-nav-link{{ $navActive(['dashboard']) }}">Home</a>
        @if(!empty($currentEntity))
            <a href="{{ route('workspace') }}" class="dms-nav-link{{ $navActive(['workspace']) }}">Workspace</a>
            <a href="{{ entity_route('documents.upload') }}" class="dms-nav-link{{ $navActive(['documents.upload', 'documents.store']) }}">Upload</a>
            <a href="{{ entity_route('documents.search') }}" class="dms-nav-link{{ $navActive(['documents.search']) }}">Search</a>
            <a href="{{ entity_route('summary-dashboard') }}" class="dms-nav-link{{ $navActive(['summary-dashboard', 'summary-dashboard.download']) }}">Dashboard</a>
            @role('Admin')
                <a href="{{ entity_route('projects.index') }}" class="dms-nav-link{{ $navActive(['projects.index', 'projects.create', 'projects.store', 'projects.edit', 'projects.update']) }}">Project Master</a>
            @endrole
        @endif
        @role('Admin')
            <a href="{{ route('entities.index') }}" class="dms-nav-link{{ $navActive(['entities.*']) }}">Entities</a>
            <a href="{{ route('disciplines.index') }}" class="dms-nav-link{{ $navActive(['disciplines.*']) }}">Disciplines</a>
            <a href="{{ route('folders.index') }}" class="dms-nav-link{{ $navActive(['folders.*']) }}">Folders</a>
            <a href="{{ route('user-access.index') }}" class="dms-nav-link{{ $navActive(['user-access.*']) }}">User Access</a>
            <a href="{{ route('user-activities.index') }}" class="dms-nav-link{{ $navActive(['user-activities.*']) }}">Activity Log</a>
        @endrole
        <a href="{{ route('documents.trash') }}" class="dms-nav-link{{ $navActive(['documents.trash', 'documents.trash.*']) }}">Trash</a>
    </nav>
</header>

<div class="layout-row">
    <aside class="sidebar">
        @php
            $accessService = app(\App\Services\DocumentAccessService::class);
            // Show every folder the user is allowed to access (do not hide empty ones).
            $sidebarTree = $accessService->accessibleSidebarFolderTree(auth()->user());
            $sidebarFolders = collect($sidebarTree)
                ->map(fn (array $items, string $name): array => ['name' => $name, 'items' => $items])
                ->values()
                ->all();
        @endphp

        <div class="sidebar-shell">
            <ul class="folder-menu" id="folderMenu">
                @foreach($sidebarFolders as $index => $folder)
                    <li class="folder-item">
                        <button
                            type="button"
                            class="folder-toggle"
                            data-folder-toggle
                            data-folder-index="{{ $index }}"
                            aria-expanded="false"
                        >
                            <span>{{ $folder['name'] }}</span>
                            <span class="caret">&#9662;</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>
    <main class="main-content">
        <div id="folderBlocksMain" class="folder-blocks-main" hidden>
            <div class="folder-blocks-main-inner">
                <h2 class="folder-blocks-main-heading" id="folderBlocksTitle">Document types</h2>
                <div class="folder-blocks-main-grid" id="folderBlocksGrid"></div>
            </div>
        </div>
        <div class="container" id="mainPageContainer">
            @yield('content')
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var folderData = @json($sidebarFolders);
        var toggles = document.querySelectorAll('[data-folder-toggle]');
        var folderBlocksMain = document.getElementById('folderBlocksMain');
        var folderBlocksTitle = document.getElementById('folderBlocksTitle');
        var folderBlocksGrid = document.getElementById('folderBlocksGrid');
        var mainPageContainer = document.getElementById('mainPageContainer');
        var params = new URLSearchParams(window.location.search);
        var currentMainFolder = params.get('main_folder') || '';
        var currentSubfolder = params.get('document_type') || '';
        var currentProjectId = params.get('project_id') || '';
        var currentEntityId = @json($currentEntityId ?? (request()->filled('entity_id') ? (int) request('entity_id') : null)) || params.get('entity_id') || '';

        function setFolderBlocksOpen(open) {
            if (open) {
                folderBlocksMain.hidden = false;
                mainPageContainer.style.display = 'none';
            } else {
                folderBlocksMain.hidden = true;
                mainPageContainer.style.display = '';
            }
        }

        function renderSubfolders(index) {
            var selectedFolder = folderData[index];
            var items = selectedFolder && selectedFolder.items ? selectedFolder.items : [];
            folderBlocksTitle.textContent = selectedFolder ? selectedFolder.name : 'Document types';
            folderBlocksGrid.innerHTML = '';

            if (!items.length) {
                var empty = document.createElement('div');
                empty.className = 'folder-blocks-main-empty';
                empty.textContent = 'No document types.';
                folderBlocksGrid.appendChild(empty);
                return;
            }

            // "All in this category" — e.g. all Financial Documents subfolders
            var allBlock = document.createElement('div');
            allBlock.className = 'folder-block-card';
            var allLink = document.createElement('a');
            var allHref = '{{ route('documents.search') }}?from_sidebar=1&main_folder=' + encodeURIComponent(selectedFolder.name);
            if (currentEntityId) {
                allHref += '&entity_id=' + encodeURIComponent(currentEntityId);
            }
            if (currentProjectId) {
                allHref += '&project_id=' + encodeURIComponent(currentProjectId);
            }
            allLink.href = allHref;
            if (currentMainFolder === selectedFolder.name && !currentSubfolder) {
                allLink.classList.add('is-active');
            }
            var allIcon = document.createElement('span');
            allIcon.className = 'folder-block-icon';
            allIcon.setAttribute('aria-hidden', 'true');
            allIcon.textContent = '📁';
            var allTitle = document.createElement('span');
            allTitle.className = 'folder-block-title';
            allTitle.textContent = 'All ' + selectedFolder.name;
            allLink.appendChild(allIcon);
            allLink.appendChild(allTitle);
            allBlock.appendChild(allLink);
            folderBlocksGrid.appendChild(allBlock);

            items.forEach(function (item) {
                var block = document.createElement('div');
                block.className = 'folder-block-card';
                var link = document.createElement('a');
                var href = '{{ route('documents.search') }}?from_sidebar=1&main_folder=' + encodeURIComponent(selectedFolder.name) + '&document_type=' + encodeURIComponent(item);
                if (currentEntityId) {
                    href += '&entity_id=' + encodeURIComponent(currentEntityId);
                }
                if (currentProjectId) {
                    href += '&project_id=' + encodeURIComponent(currentProjectId);
                }
                link.href = href;
                var icon = document.createElement('span');
                icon.className = 'folder-block-icon';
                icon.setAttribute('aria-hidden', 'true');
                icon.textContent = '📁';
                var titleEl = document.createElement('span');
                titleEl.className = 'folder-block-title';
                titleEl.textContent = item;
                link.appendChild(icon);
                link.appendChild(titleEl);
                if (currentSubfolder && currentSubfolder === item) {
                    link.classList.add('is-active');
                }
                block.appendChild(link);
                folderBlocksGrid.appendChild(block);
            });
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var folderIndex = toggle.getAttribute('data-folder-index');

                if (toggle.classList.contains('active')) {
                    toggle.classList.remove('active');
                    toggle.setAttribute('aria-expanded', 'false');
                    setFolderBlocksOpen(false);
                    return;
                }

                toggles.forEach(function (btn) {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-expanded', 'false');
                });

                toggle.classList.add('active');
                toggle.setAttribute('aria-expanded', 'true');
                renderSubfolders(folderIndex);
                setFolderBlocksOpen(true);
            });
        });

        if (currentMainFolder && !currentSubfolder) {
            var folderIndexOpen = folderData.findIndex(function (folder) {
                return folder.name === currentMainFolder;
            });
            if (folderIndexOpen >= 0 && toggles[folderIndexOpen]) {
                toggles.forEach(function (btn) {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-expanded', 'false');
                });
                toggles[folderIndexOpen].classList.add('active');
                toggles[folderIndexOpen].setAttribute('aria-expanded', 'true');
                renderSubfolders(String(folderIndexOpen));
                setFolderBlocksOpen(true);
            }
        } else if (currentSubfolder) {
            var inferredIndex = folderData.findIndex(function (folder) {
                return Array.isArray(folder.items) && folder.items.indexOf(currentSubfolder) !== -1;
            });
            if (inferredIndex >= 0 && toggles[inferredIndex]) {
                toggles[inferredIndex].classList.add('active');
                toggles[inferredIndex].setAttribute('aria-expanded', 'true');
            }
        }
    });
</script>

</body>
</html>