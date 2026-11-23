<!DOCTYPE html>
<html>
<head>
    <title>Tanseeq DMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/dms-enterprise.css', 'resources/js/app.js'])

    <style>
        :root {
            --navy: #0b1f3a;
            --navy-hover: #163a66;
            --navy-soft: #1e4d7b;
            --accent: #3b82f6;
            --accent-dark: #1d4ed8;
            --gold: #3b82f6;
            --gold-dark: #1d4ed8;
            --green: #15803d;
            --green-soft: #dcfce7;
            --green-text: #166534;
            --bg-page: #e8eef5;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text: #0b1f3a;
            --text-muted: #64748b;
            --sidebar-bg: #0b1f3a;
            --sidebar-text: #e2e8f0;
            --sidebar-muted: #94a3b8;
            --sidebar-active: #1e4d7b;
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
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.25rem; }
        h3 { font-size: 1.05rem; }
        h4 { font-size: 0.95rem; }

        strong, b, th, label, button {
            font-weight: 600;
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
            box-shadow: 0 2px 12px rgba(11, 31, 58, 0.28);
            border-bottom: none;
        }

        .dms-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: var(--header-top-h);
            padding: 0 28px;
            background: var(--navy);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .dms-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            flex: 0 1 auto;
        }

        .dms-topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .dms-brand-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .dms-entity-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            flex-shrink: 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 3px;
            box-sizing: border-box;
        }

        .dms-entity-logo-initials {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-dark);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.35);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 0;
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
            color: #fff;
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
            color: rgba(255, 255, 255, 0.72);
        }

        .dms-date-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.78rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .dms-date-pill svg {
            width: 14px;
            height: 14px;
            color: var(--accent);
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
            background: var(--accent-dark);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.85);
            box-shadow: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .dms-user-dropdown[open] .dms-user-avatar,
        .dms-user-dropdown > summary:hover .dms-user-avatar {
            border-color: var(--accent);
        }

        .dms-user-dropdown-panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 140px;
            padding: 8px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
            z-index: 120;
        }

        button.dms-logout-btn,
        .dms-logout-btn {
            background: #fff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0b1f3a !important;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            padding: 10px 12px !important;
            width: 100%;
            text-align: left;
            border-radius: 6px;
        }

        button.dms-logout-btn:hover,
        .dms-logout-btn:hover {
            background: #eff6ff !important;
            border-color: #93c5fd !important;
            color: #1d4ed8 !important;
        }

        .dms-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 4px 0;
            min-height: var(--header-nav-h);
            padding: 0 20px;
            background: #0a1a31;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .dms-nav-link {
            display: inline-block;
            padding: 12px 14px 10px;
            color: rgba(255, 255, 255, 0.78);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            text-transform: none;
            border-bottom: 3px solid transparent;
            transition: color 0.15s, border-color 0.15s, background 0.15s;
            white-space: nowrap;
        }

        .dms-home-form {
            margin: 0;
            display: inline;
        }

        button.dms-nav-button.dms-nav-link {
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-family: inherit;
            appearance: none;
            color: rgba(255, 255, 255, 0.78);
        }

        .dms-nav-upload {
            margin-left: 6px;
            margin-right: 4px;
            padding: 8px 14px !important;
            border-radius: 6px !important;
            border-bottom: none !important;
            background: var(--accent) !important;
            color: #fff !important;
            font-size: 0.82rem !important;
            font-weight: 600 !important;
            align-self: center;
        }

        .dms-nav-upload:hover,
        .dms-nav-upload.is-active {
            background: var(--accent-dark) !important;
            color: #fff !important;
            border-bottom-color: transparent !important;
        }

        .dms-nav-dropdown {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .dms-nav-dropdown > summary {
            list-style: none;
            cursor: pointer;
        }

        .dms-nav-dropdown > summary::-webkit-details-marker { display: none; }

        .dms-nav-dropdown-panel {
            position: absolute;
            top: calc(100% - 2px);
            left: 0;
            min-width: 200px;
            padding: 8px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
            z-index: 130;
        }

        .dms-nav-dropdown-panel a {
            display: block;
            padding: 9px 10px;
            border-radius: 6px;
            color: #0b1f3a !important;
            text-decoration: none !important;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .dms-nav-dropdown-panel a:hover {
            background: #eff6ff;
            color: #1d4ed8 !important;
        }

        .dms-global-search {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 280px;
            max-width: 420px;
            margin: 0 16px;
            min-width: 180px;
        }

        .dms-global-search input {
            width: 100%;
            margin: 0 !important;
            padding: 9px 12px 9px 34px !important;
            border-radius: 8px !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            background: rgba(255, 255, 255, 0.08) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%94a3b8' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='M20 20l-3-3'/%3E%3C/svg%3E") no-repeat 10px center !important;
            color: #fff !important;
            font-size: 0.85rem !important;
        }

        .dms-global-search input::placeholder {
            color: rgba(226, 232, 240, 0.7);
        }

        .dms-global-search input:focus {
            outline: none;
            border-color: rgba(59, 130, 246, 0.7) !important;
            background-color: rgba(255, 255, 255, 0.12) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }

        .dms-greeting {
            color: rgba(226, 232, 240, 0.9);
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .dms-company-switcher {
            position: relative;
            margin-left: 10px;
        }

        .dms-company-switcher > summary {
            list-style: none;
            cursor: pointer;
        }

        .dms-company-switcher > summary::-webkit-details-marker { display: none; }

        .dms-company-switcher-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            min-width: 260px;
            max-height: 320px;
            overflow: auto;
            padding: 8px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
            z-index: 140;
        }

        .dms-company-switcher-panel form { margin: 0; }

        .dms-company-switcher-panel button {
            width: 100%;
            text-align: left;
            background: #fff !important;
            color: #0b1f3a !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 9px 10px !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
        }

        .dms-company-switcher-panel button:hover,
        .dms-company-switcher-panel button.is-current {
            background: #eff6ff !important;
            color: #1d4ed8 !important;
        }

        .dms-company-switcher-panel .sector-label {
            display: block;
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 2px;
            font-weight: 500;
        }

        .dms-nav-link:hover {
            color: #fff;
        }

        .dms-nav-link.is-active {
            border-bottom-color: var(--accent);
            color: #fff;
        }

        .dms-entity-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 12px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.18);
            border: 1px solid rgba(147, 197, 253, 0.45);
            color: #e2e8f0;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            text-transform: none;
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .dms-switch-company-form {
            display: inline-flex;
            margin: 0 0 0 10px;
        }

        button.dms-switch-company-btn,
        .dms-switch-company-btn {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border: 1px solid rgba(255, 255, 255, 0.28) !important;
            border-radius: 999px;
            padding: 6px 12px !important;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            white-space: nowrap;
        }

        button.dms-switch-company-btn:hover,
        .dms-switch-company-btn:hover {
            background: rgba(255, 255, 255, 0.18) !important;
            border-color: var(--accent) !important;
            color: #fff !important;
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

            .dms-greeting {
                display: none;
            }

            .dms-global-search {
                order: 3;
                flex: 1 1 100%;
                max-width: none;
                margin: 4px 0 0;
            }

            .dms-nav {
                justify-content: flex-start;
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 2px;
            }

            .dms-nav-link {
                padding: 12px 12px 10px;
                font-size: 0.8rem;
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

        .layout-row.no-sidebar .main-content {
            width: 100%;
        }

        .sidebar {
            width: 215px;
            min-width: 215px;
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
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
            background: rgba(255, 255, 255, 0.04);
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
            border-color: rgba(147, 197, 253, 0.35);
        }

        .sidebar .folder-toggle .caret {
            font-size: 0.8rem;
            color: var(--sidebar-muted);
        }

        .sidebar .folder-toggle.active {
            background: var(--sidebar-active);
            border-color: var(--accent);
            color: #fff;
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
            border-color: var(--accent);
            box-shadow: 0 8px 22px rgba(29, 78, 216, 0.14);
            transform: scale(1.06);
            color: var(--navy) !important;
        }

        .folder-blocks-main .folder-block-card a.is-active {
            border-color: var(--accent-dark);
            background: #fff;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.28);
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
            background: #f1f5f9;
            color: var(--text);
            border-color: #cbd5e1;
            font-weight: 600;
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
    $authUser = auth()->user();
    $userInitials = $authUser->name
        ? entity_initials($authUser->name)
        : strtoupper(substr($authUser->email, 0, 2));
    $userFirstName = trim((string) strtok((string) ($authUser->name ?? ''), ' '));
    if ($userFirstName === '') {
        $userFirstName = 'there';
    }
    $topHour = (int) now()->timezone(config('app.timezone', 'Asia/Dubai'))->format('G');
    $topPeriod = $topHour < 12 ? 'morning' : ($topHour < 17 ? 'afternoon' : 'evening');
    $navActive = fn (array $routes): string => request()->routeIs($routes) ? ' is-active' : '';
    $adminNavActive = request()->routeIs([
        'entities.*', 'disciplines.*', 'folders.*', 'user-access.*', 'user-activities.*',
        'documents.trash', 'documents.trash.*',
    ]) ? ' is-active' : '';
    $globalSearchAction = ! empty($currentEntity)
        ? entity_route('documents.search')
        : route('documents.search');
@endphp

<header class="dms-header">
    <div class="dms-topbar">
        <div class="dms-brand">
            <img
                class="dms-brand-logo"
                src="{{ asset('images/tanseeq.png') }}?v=3"
                alt="Tanseeq Investment"
            />
            <div class="dms-brand-text">
                <span class="dms-brand-title">Tanseeq Investment</span>
                <span class="dms-brand-subtitle">Document Management System</span>
            </div>
            @if(!empty($currentEntity))
                <details class="dms-company-switcher">
                    <summary class="dms-entity-badge" title="Switch company">{{ $currentEntity->name }} ▾</summary>
                    <div class="dms-company-switcher-panel">
                        @foreach(($switcherEntities ?? collect()) as $switchEntity)
                            <form method="POST" action="{{ route('entities.enter', $switchEntity->id) }}">
                                @csrf
                                <button type="submit" class="{{ (int) $switchEntity->id === (int) ($currentEntityId ?? 0) ? 'is-current' : '' }}">
                                    {{ $switchEntity->name }}
                                    <span class="sector-label">{{ $switchEntity->sector_label }}</span>
                                </button>
                            </form>
                        @endforeach
                        <form method="GET" action="{{ route('dashboard') }}" style="margin-top:6px;border-top:1px solid #e2e8f0;padding-top:6px;">
                            <button type="submit">All companies</button>
                        </form>
                    </div>
                </details>
            @endif
        </div>
        <form class="dms-global-search" method="GET" action="{{ $globalSearchAction }}" role="search">
            <label class="sr-only" for="dms-global-search-input">Search documents</label>
            <input
                id="dms-global-search-input"
                type="search"
                name="keyword"
                value="{{ request('keyword') }}"
                placeholder="Search files or project #…"
                autocomplete="off"
            >
        </form>
        <div class="dms-topbar-right">
            <span class="dms-greeting">Good {{ $topPeriod }}, {{ $userFirstName }}</span>
            <span class="dms-topbar-divider" aria-hidden="true"></span>
            <div class="dms-user-block">
                <details class="dms-user-dropdown">
                    <summary class="dms-user-avatar" title="{{ $authUser->name ?: $authUser->email }}" aria-label="Account menu">{{ $userInitials }}</summary>
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
            <a href="{{ entity_route('documents.search') }}" class="dms-nav-link{{ $navActive(['documents.search']) }}">Search</a>
            <a href="{{ entity_route('summary-dashboard') }}" class="dms-nav-link{{ $navActive(['summary-dashboard', 'summary-dashboard.download']) }}">Dashboard</a>
            @role('Admin')
                <a href="{{ entity_route('projects.index') }}" class="dms-nav-link{{ $navActive(['projects.index', 'projects.create', 'projects.store', 'projects.edit', 'projects.update']) }}">Projects</a>
            @endrole
            <a href="{{ entity_route('documents.upload') }}" class="dms-nav-link dms-nav-upload{{ $navActive(['documents.upload', 'documents.store']) }}">Upload</a>
        @endif
        @role('Admin')
            <details class="dms-nav-dropdown">
                <summary class="dms-nav-link{{ $adminNavActive }}">Admin ▾</summary>
                <div class="dms-nav-dropdown-panel">
                    <a href="{{ route('entities.index') }}">Entities</a>
                    <a href="{{ route('disciplines.index') }}">Disciplines</a>
                    <a href="{{ route('folders.index') }}">Folders</a>
                    <a href="{{ route('user-access.index') }}">User Access</a>
                    <a href="{{ route('user-activities.index') }}">Activity Log</a>
                    <a href="{{ route('documents.trash') }}">Trash</a>
                </div>
            </details>
        @else
            <a href="{{ route('documents.trash') }}" class="dms-nav-link{{ $navActive(['documents.trash', 'documents.trash.*']) }}">Trash</a>
        @endrole
    </nav>
</header>

<div class="layout-row{{ empty($currentEntity) ? ' no-sidebar' : '' }}">
    @php
        $sidebarFolders = [];
    @endphp
    @if(!empty($currentEntity))
        <aside class="sidebar">
            @php
                $accessService = app(\App\Services\DocumentAccessService::class);
                $sidebarTree = $accessService->accessibleSidebarFolderTree(
                    auth()->user(),
                    (int) ($currentEntityId ?? $currentEntity->id)
                );
                $sidebarFolders = collect($sidebarTree)
                    ->map(fn (array $items, string $name): array => ['name' => $name, 'items' => $items])
                    ->values()
                    ->all();
            @endphp

            <div class="sidebar-shell">
                <ul class="folder-menu" id="folderMenu">
                    @forelse($sidebarFolders as $index => $folder)
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
                    @empty
                        <li class="folder-item" style="color:var(--text-muted);font-size:0.85rem;padding:8px 4px;">
                            No folders available for your access.
                        </li>
                    @endforelse
                </ul>
            </div>
        </aside>
    @endif
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
        var folderData = @json($sidebarFolders ?? []);
        var toggles = document.querySelectorAll('.sidebar [data-folder-toggle]');
        var folderBlocksMain = document.getElementById('folderBlocksMain');
        var folderBlocksTitle = document.getElementById('folderBlocksTitle');
        var folderBlocksGrid = document.getElementById('folderBlocksGrid');
        var mainPageContainer = document.getElementById('mainPageContainer');
        if (!folderBlocksMain || !folderBlocksGrid || !mainPageContainer || !toggles.length) {
            return;
        }
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