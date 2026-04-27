<!DOCTYPE html>
<html>
<head>
    <title>Document Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --navy: #212d3e;
            --navy-hover: #2d3a52;
            --navy-soft: #334155;
            --gold: #c4a47c;
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
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--bg-page);
            margin: 0;
            color: var(--text);
            font-size: 14px;
            font-weight: 400;
            line-height: 1.4;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 500;
            line-height: 1.3;
        }

        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.2rem; }
        h3 { font-size: 1.05rem; }
        h4 { font-size: 1rem; }

        strong, b, th, label, button {
            font-weight: 400;
        }

        .main-content a {
            color: var(--gold-dark);
        }

        .main-content a:hover {
            color: var(--gold);
        }

        .navbar {
            background: var(--navy);
            padding: 15px 30px;
            color: #fff;
            display: flex;
            justify-content: space-between;
        }

        .navbar a {
            color: #fff;
            text-decoration: none;
            margin-left: 20px;
        }

        .navbar .logout-form {
            display: inline;
            margin-left: 20px;
        }

        .navbar .logout-btn {
            background: transparent;
            color: #fff;
            border: none;
            padding: 0;
            font: inherit;
            cursor: pointer;
        }

        .navbar a:hover,
        .navbar .logout-btn:hover {
            color: var(--gold);
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
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
            min-height: calc(100vh - 52px);
        }

        .sidebar {
            width: 280px;
            min-width: 280px;
            background: var(--navy);
            border-right: 1px solid rgba(0, 0, 0, 0.15);
            padding: 16px;
            overflow-y: auto;
        }

        .sidebar h3 {
            margin: 0 0 12px 0;
            font-size: 0.9rem;
            color: var(--sidebar-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
            padding: 14px 16px;
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
            border-color: rgba(196, 164, 124, 0.35);
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
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--navy) !important;
            text-decoration: none !important;
            font-size: 0.82rem;
            font-weight: 600;
            line-height: 1.3;
            box-shadow: 0 1px 3px rgba(33, 45, 62, 0.06);
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s;
        }

        .folder-blocks-main .folder-block-card a:hover {
            border-color: rgba(196, 164, 124, 0.85);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
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

        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
                min-width: 240px;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Document Management System</strong></div>
    <div>
        <a href="{{ route('dashboard') }}">Home</a>
        <a href="{{ route('project-dashboard') }}">Project Dashboard</a>
        <a href="{{ route('entities.index') }}">Entities</a>
        <a href="{{ route('projects.index') }}">Project Master</a>
        <a href="{{ route('disciplines.index') }}">Disciplines</a>
        <a href="{{ route('documents.upload') }}">Upload</a>
        <a href="{{ route('documents.search') }}">Search</a>
        <form method="POST" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>
</div>

<div class="layout-row">
    <aside class="sidebar">
        <div style="background:#ffffff; border-radius:14px; padding:14px; margin-bottom:14px; border:1px solid rgba(255,255,255,0.18); display:flex; align-items:center; justify-content:center; overflow:hidden;">
            <img
                src="{{ asset('images/tanseeq.png') }}"
                alt="TANSEEQ INVESTMENT"
                style="width: 100%; max-width: 220px; height: auto; object-fit: contain; object-position: center; display:block;"
            />
        </div>
        <h3>Folders</h3>
        @php
            $sidebarFolders = [
                [
                    'name' => 'Financial Documents',
                    'items' => [
                        'Bank Gurantees',
                        'Invoice',
                        'Payment Voucher',
                        'Proforma Invoice',
                        'Receipt Voucher',
                        'Sales Credit Note',
                        'Supplier Delivery Note',
                        'Supplier Invoice',
                        'Supplier Time Sheets',
                    ],
                ],
                [
                    'name' => 'General Correspondence',
                    'items' => [
                        'Incoming Or Outgoing Letter',
                        'Internal Memo',
                        'KPI Report',
                        'Monthly Report',
                        'Payment Certificate',
                        'Project Award Notification',
                        'Snags',
                        'Spare Parts',
                    ],
                ],
                [
                    'name' => 'Project Correspondence',
                    'items' => [
                        'Defect Liability Certificate',
                        'Engineers Correspondences',
                        'Engineers Instruction',
                        'MOM',
                        'NCR',
                        'Operation And Maintenance Manual',
                        'Payment Application',
                        'Quality Observation Report',
                        'Request For Information',
                        'Site Observation Report',
                        'Site Incident Report',
                        'Taking Over Certificate',
                        'Testing And Commissioning',
                        'Variation',
                        'Warranty By Us',
                        'Change Request',
                        'Design Calculation',
                        'Confirmation Of Verbal Instruction',
                        'Project Commercial Documents',
                    ],
                ],
                [
                    'name' => 'Purchase Documents',
                    'items' => [
                        'Catalogs',
                        'Delivery Order',
                        'Enquireis',
                        'Good Receipt Note',
                        'Material Issue Note',
                        'Material Return Note',
                        'Purchase Order',
                        'Purchase Request',
                        'Quotations',
                        'Sales Order',
                        'Trade License certificate',
                        'VAT Registration Certificate',
                        'Vendor Registration certificate',
                    ],
                ],
                [
                    'name' => 'Transmittals Documents',
                    'items' => [
                        'As Built Drawing Submittal',
                        'Material Submittal',
                        'Material Inspection Request',
                        'Method Statement',
                        'Prequalification',
                        'Shop Drawing',
                        'Work Inspection',
                        'Document Transmittal',
                        'Material Sample',
                    ],
                ],
            ];
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
                <h2 class="folder-blocks-main-heading" id="folderBlocksTitle">Subfolders</h2>
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
        var currentEntityId = params.get('entity_id') || '';

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
            folderBlocksTitle.textContent = selectedFolder ? selectedFolder.name : 'Subfolders';
            folderBlocksGrid.innerHTML = '';

            if (!items.length) {
                var empty = document.createElement('div');
                empty.className = 'folder-blocks-main-empty';
                empty.textContent = 'No subfolders.';
                folderBlocksGrid.appendChild(empty);
                return;
            }

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