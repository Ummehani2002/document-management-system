@extends('layouts.app')

@section('content')

<div class="dms-page-header">
    <div>
        <p class="dms-page-kicker">Administration</p>
        <h2>Entities</h2>
        <p class="dms-page-lead">Company register with short codes used across the DMS.</p>
    </div>
    <div class="dms-page-actions">
        <a href="{{ route('entities.create') }}" style="display:inline-block;padding:10px 18px;background:#0b1f3a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">Add Entity</a>
    </div>
</div>

@if (session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

@if($entities->isEmpty())
    <div class="card">
        <p>No entities yet. <a href="{{ route('entities.create') }}">Create an entity</a>, open that company, then use <strong>Project Master</strong> to add projects.</p>
    </div>
@else
    <div class="card dms-grid-wrap">
        <table class="dms-grid-table">
            <thead>
                <tr>
                    <th>Short code</th>
                    <th>Entity</th>
                    <th>Type</th>
                    <th>Projects</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entities as $entity)
                    <tr>
                        <td><strong>{{ $entity->displayCode() }}</strong></td>
                        <td>{{ $entity->name }}</td>
                        <td>{{ $entity->businessTypeLabel() }}</td>
                        <td>{{ $entity->projects_count }} project(s)</td>
                        <td class="text-right" style="white-space: nowrap;">
                            <form action="{{ route('entities.enter', $entity) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none;border:none;padding:0;color:inherit;cursor:pointer;text-decoration:underline;font-weight:600;">Open</button>
                            </form>
                            &nbsp;·&nbsp;
                            <a href="{{ route('entities.edit', $entity) }}">Edit</a>
                            @role('Admin')
                                &nbsp;·&nbsp;
                                <form action="{{ route('entities.destroy', $entity) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this entity? Its projects and documents will move to Trash and can be restored.');">
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
        <div style="margin-top: 16px; padding: 0 16px 16px;">{{ $entities->links() }}</div>
    </div>
@endif

@endsection
