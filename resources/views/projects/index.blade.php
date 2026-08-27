@extends('layouts.app')

@section('content')

<h2>Project Master — {{ $entity->name }}</h2>

@if (session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

<p style="margin-bottom: 16px;">
    <a href="{{ entity_route('projects.create') }}" style="display: inline-block; padding: 10px 20px; background: #212d3e; color: white; text-decoration: none; border-radius: 5px;">Add Project</a>
    <a href="{{ route('workspace') }}" style="margin-left: 12px;">← Back to workspace</a>
</p>

@if($projects->isEmpty())
    <div class="card">
        <p>No projects yet for this company. <a href="{{ entity_route('projects.create') }}">Add a project</a>.</p>
    </div>
@else
    <div class="card dms-grid-wrap">
        <table class="dms-grid-table min-w-xl">
            <thead>
                <tr>
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
                        <td>{{ $project->project_number }}</td>
                        <td>{{ $project->project_name }}</td>
                        <td>{{ $project->client_name ?? '-' }}</td>
                        <td>{{ $project->consultant ?? '-' }}</td>
                        <td>
                            {{ $project->project_manager ?? '-' }}
                            @if($project->project_manager_email)
                                <div style="color:#64748b; font-size:0.82rem; margin-top:2px;">{{ $project->project_manager_email }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $project->document_controller ?? '-' }}
                            @if($project->document_controller_email)
                                <div style="color:#64748b; font-size:0.82rem; margin-top:2px;">{{ $project->document_controller_email }}</div>
                            @endif
                        </td>
                        <td class="text-right" style="white-space: nowrap;">
                            <a href="{{ entity_route('documents.upload') }}">Upload</a>
                            &nbsp;·&nbsp;
                            <a href="{{ route('projects.edit', $project) }}">Edit</a>
                            @role('Admin')
                                &nbsp;·&nbsp;
                                <form action="{{ route('projects.destroy', $project) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this project? Its documents will move to Trash and can be restored.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background: none; border: none; padding: 0; color: #b91c1c; cursor: pointer; text-decoration: underline;">Delete</button>
                                </form>
                            @endrole
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top: 16px; padding: 0 16px 16px;">{{ $projects->links() }}</div>
    </div>
@endif

@endsection
