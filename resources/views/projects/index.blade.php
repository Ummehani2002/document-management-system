@extends('layouts.app')

@section('content')

<h2>Project Master</h2>


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

<p style="margin-bottom: 16px;"><a href="{{ route('projects.create') }}" style="display: inline-block; padding: 10px 20px; background: #212d3e; color: white; text-decoration: none; border-radius: 5px;">Add Project</a></p>

@if($projects->isEmpty())
    <div class="card">
        <p>No projects yet. <a href="{{ route('projects.create') }}">Add a project</a> (create an <a href="{{ route('entities.create') }}">entity</a> first if needed).</p>
    </div>
@else
    <div class="card dms-grid-wrap">
        <table class="dms-grid-table min-w-lg">
            <thead>
                <tr>
                    <th>Entity</th>
                    <th>Project #</th>
                    <th>Project Name</th>
                    <th>Client</th>
                    <th>Consultant</th>
                    <th>Project Manager</th>
                    <th>Document Controller</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td>{{ $project->entity->name ?? '-' }}</td>
                        <td>{{ $project->project_number }}</td>
                        <td>{{ $project->project_name }}</td>
                        <td>{{ $project->client_name ?? '-' }}</td>
                        <td>{{ $project->consultant ?? '-' }}</td>
                        <td>{{ $project->project_manager ?? '-' }}</td>
                        <td>{{ $project->document_controller ?? '-' }}</td>
                        <td class="text-right" style="white-space: nowrap;">
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
        <div style="margin-top: 16px; padding: 0 16px 16px;">{{ $projects->links() }}</div>
    </div>
@endif

@endsection
