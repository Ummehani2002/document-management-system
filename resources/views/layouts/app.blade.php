<!DOCTYPE html>
<html>
<head>
    <title>Document Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
        }

        .navbar {
            background: #1e293b;
            padding: 15px 30px;
            color: white;
            display: flex;
            justify-content: space-between;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        h2 {
            margin-top: 0;
        }

        input[type="file"], input[type="text"], select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            max-width: 100%;
            box-sizing: border-box;
        }

        select { margin-top: 4px; }

        label { display: block; font-weight: 600; margin-bottom: 4px; }

        .btn-primary, button[type="submit"] {
            background: #1e293b;
        }

        button {
            background: #1e293b;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover, .btn-primary:hover {
            background: #334155;
        }

        .success {
            background: #d1fae5;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            color: #065f46;
        }

        .card {
            border: 1px solid #e5e7eb;
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
            background: #f1f5f9;
            border-right: 1px solid #e2e8f0;
            padding: 16px;
            overflow-y: auto;
        }

        .sidebar h3 {
            margin: 0 0 12px 0;
            font-size: 0.9rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0 0 16px 0;
        }

        .sidebar ul ul {
            margin: 4px 0 4px 12px;
            padding-left: 8px;
            border-left: 2px solid #cbd5e1;
        }

        .sidebar li {
            margin: 4px 0;
            font-size: 0.9rem;
        }

        .sidebar a {
            color: #1e293b;
            text-decoration: none;
        }

        .sidebar a:hover {
            text-decoration: underline;
        }

        .sidebar .entity-name {
            font-weight: 600;
            color: #0f172a;
        }

        .sidebar .project-name {
            color: #334155;
        }

        .sidebar .folder-name {
            color: #64748b;
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Company DMS</strong></div>
    <div>
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('entities.index') }}">Entities</a>
        <a href="{{ route('projects.index') }}">Project Master</a>
        <a href="{{ route('documents.upload') }}">Upload</a>
        <a href="{{ route('documents.search') }}">Search</a>
    </div>
</div>

<div class="layout-row">
    <aside class="sidebar">
        <h3>Folders only</h3>
        <p style="color: #64748b; font-size: 0.8rem; margin: 0 0 12px 0;">Click a folder to see PDFs in that folder.</p>
        @isset($entities)
            @if($entities->isEmpty())
                <p style="color: #64748b; font-size: 0.9rem;">No folders yet. Upload PDFs to see Entity → Project → Folder here.</p>
            @else
                @foreach($entities as $entity)
                    <ul>
                        <li class="entity-name">{{ $entity->name }}</li>
                        @foreach($entity->projects as $project)
                            <li>
                                <span class="project-name">{{ $project->project_number }}</span>
                                @php $folders = ($foldersByProject[$project->id] ?? collect())->unique('document_type')->values(); @endphp
                                @if($folders->isEmpty())
                                    <ul><li class="folder-name">—</li></ul>
                                @else
                                    <ul>
                                        @foreach($folders as $doc)
                                            <li class="folder-name">
                                                <a href="{{ route('documents.search', ['entity_id' => $entity->id, 'project_id' => $project->id, 'document_type' => $doc->document_type]) }}">{{ $doc->document_type }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            @endif
        @else
            <p style="color: #64748b; font-size: 0.9rem;">No folders yet.</p>
        @endisset
    </aside>
    <main class="main-content">
        <div class="container">
            @yield('content')
        </div>
    </main>
</div>

</body>
</html>