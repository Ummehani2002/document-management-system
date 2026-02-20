@extends('layouts.app')

@section('content')

<h2>Project Master – Edit Project</h2>
<p style="color: #64748b; margin-bottom: 20px;"><a href="{{ route('projects.index') }}">← Back to Project Master</a></p>

@if ($errors->any())
    <div class="card" style="background: #fef2f2; border-color: #fecaca;">
        <ul style="margin: 0; padding-left: 20px; color: #b91c1c;">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('projects.update', $project) }}">
    @csrf
    @method('PATCH')

    <div class="card">
        <label for="entity_id">Entity *</label>
        <select name="entity_id" id="entity_id" required>
            @foreach($entities as $e)
                <option value="{{ $e->id }}" {{ old('entity_id', $project->entity_id) == $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="card">
        <label for="project_number">Project Number *</label>
        <input type="text" name="project_number" id="project_number" value="{{ old('project_number', $project->project_number) }}" placeholder="Enter project number" required>
    </div>

    <div class="card">
        <label for="project_name">Project Name *</label>
        <input type="text" name="project_name" id="project_name" value="{{ old('project_name', $project->project_name) }}" placeholder="Enter project name" required>
    </div>

    <div class="card">
        <label for="project_manager">Project Manager</label>
        <input type="text" name="project_manager" id="project_manager" value="{{ old('project_manager', $project->project_manager) }}" placeholder="Enter project manager name">
    </div>

    <div class="card">
        <label for="client_name">Client Name</label>
        <input type="text" name="client_name" id="client_name" value="{{ old('client_name', $project->client_name) }}" placeholder="Enter client name">
    </div>

    <div class="card">
        <label for="consultant">Consultant</label>
        <input type="text" name="consultant" id="consultant" value="{{ old('consultant', $project->consultant) }}" placeholder="Enter consultant name">
    </div>

    <div class="card">
        <label for="document_controller">Document Controller</label>
        <input type="text" name="document_controller" id="document_controller" value="{{ old('document_controller', $project->document_controller) }}" placeholder="Enter document controller name">
    </div>

    <div style="margin-top: 20px;">
        <button type="submit">Save Project</button>
        <a href="{{ route('projects.index') }}" style="margin-left: 12px; color: #64748b;">Cancel</a>
    </div>
</form>

@endsection
