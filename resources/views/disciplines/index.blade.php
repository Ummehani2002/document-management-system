@extends('layouts.app')

@section('content')

<h2>Discipline Master</h2>

@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

<p style="margin-bottom: 16px;">
    <a href="{{ route('disciplines.create') }}" style="display: inline-block; padding: 10px 20px; background: #212d3e; color: white; text-decoration: none; border-radius: 5px;">Add Discipline</a>
</p>

@if($disciplines->isEmpty())
    <div class="card">
        <p>No disciplines yet. <a href="{{ route('disciplines.create') }}">Add a discipline</a> — it will appear in Upload and Search.</p>
    </div>
@else
    <div class="card dms-grid-wrap">
        <table class="dms-grid-table">
            <thead>
                <tr>
                    <th>Discipline</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($disciplines as $discipline)
                    <tr>
                        <td>{{ $discipline->name }}</td>
                        <td class="text-right" style="white-space: nowrap;">
                            <a href="{{ route('disciplines.edit', $discipline) }}">Edit</a>
                            &nbsp;·&nbsp;
                            <form action="{{ route('disciplines.destroy', $discipline) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this discipline?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background: none; border: none; padding: 0; color: #b91c1c; cursor: pointer; text-decoration: underline;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top: 16px; padding: 0 16px 16px;">{{ $disciplines->links() }}</div>
    </div>
@endif

@endsection
