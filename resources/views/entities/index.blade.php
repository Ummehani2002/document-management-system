@extends('layouts.app')

@section('content')

<h2>All Entities</h2>
<p style="color: #64748b; margin-bottom: 20px;">Add and manage entities (e.g. companies). Then add projects under each in Project Master.</p>


@if (session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

<p style="margin-bottom: 16px;"><a href="{{ route('entities.create') }}" style="display: inline-block; padding: 10px 20px; background: #1e293b; color: white; text-decoration: none; border-radius: 5px;">Add Entity</a></p>

@if($entities->isEmpty())
    <div class="card">
        <p>No entities yet. <a href="{{ route('entities.create') }}">Create an entity</a>, then add <a href="{{ route('projects.index') }}">projects</a> in Project Master. On Upload you select Entity and Project.</p>
    </div>
@else
    <div class="card">
        <ul style="list-style: none; padding: 0; margin: 0;">
            @foreach ($entities as $entity)
                <li style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                    <span><strong>{{ $entity->name }}</strong> <span style="color: #64748b;">({{ $entity->projects_count }} project(s))</span></span>
                    <span>
                        <a href="{{ route('projects.index', ['entity_id' => $entity->id]) }}">Projects</a>
                        &nbsp;·&nbsp;
                        <a href="{{ route('entities.edit', $entity) }}">Edit</a>
                        &nbsp;·&nbsp;
                        <form action="{{ route('entities.destroy', $entity) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this entity and all its projects?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background: none; border: none; padding: 0; color: #b91c1c; cursor: pointer; text-decoration: underline;">Delete</button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>
        <div style="margin-top: 16px;">{{ $entities->links() }}</div>
    </div>
@endif

@endsection
