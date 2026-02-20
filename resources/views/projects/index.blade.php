@extends('layouts.app')

@section('content')

<h2>Project Master</h2>
<p style="color: #64748b; margin-bottom: 20px;">Add and manage all projects. Select entity when adding. On Upload you select entity and project.</p>

@if (session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

<div class="card" style="margin-bottom: 20px;">
    <form method="GET" action="{{ route('projects.index') }}" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
        <div>
            <label>Filter by Entity</label>
            <select name="entity_id" style="width: 220px;">
                <option value="">All entities</option>
                @foreach($entities as $e)
                    <option value="{{ $e->id }}" {{ request('entity_id') == $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Filter</button>
    </form>
</div>

<p style="margin-bottom: 16px;"><a href="{{ route('projects.create') }}" style="display: inline-block; padding: 10px 20px; background: #1e293b; color: white; text-decoration: none; border-radius: 5px;">Add Project</a></p>

@if($projects->isEmpty())
    <div class="card">
        <p>No projects yet. <a href="{{ route('projects.create') }}">Add a project</a> (create an <a href="{{ route('entities.create') }}">entity</a> first if needed).</p>
    </div>
@else
    <div class="card" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #e5e7eb;">
                    <th style="text-align: left; padding: 10px;">Entity</th>
                    <th style="text-align: left; padding: 10px;">Project #</th>
                    <th style="text-align: left; padding: 10px;">Project Name</th>
                    <th style="text-align: left; padding: 10px;">Client</th>
                    <th style="text-align: left; padding: 10px;">Docs</th>
                    <th style="text-align: right; padding: 10px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px;">{{ $project->entity->name ?? '-' }}</td>
                        <td style="padding: 10px;">{{ $project->project_number }}</td>
                        <td style="padding: 10px;">{{ $project->project_name }}</td>
                        <td style="padding: 10px;">{{ $project->client_name ?? '-' }}</td>
                        <td style="padding: 10px;">{{ $project->documents_count }}</td>
                        <td style="padding: 10px; text-align: right;">
                            <a href="{{ route('documents.upload') }}">Upload</a>
                            &nbsp;·&nbsp;
                            <a href="{{ route('projects.edit', $project) }}">Edit</a>
                            &nbsp;·&nbsp;
                            <form action="{{ route('projects.destroy', $project) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this project and all its documents?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background: none; border: none; padding: 0; color: #b91c1c; cursor: pointer; text-decoration: underline;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top: 16px;">{{ $projects->links() }}</div>
    </div>
@endif

@endsection
