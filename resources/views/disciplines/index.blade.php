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
    <div class="card">
        <ul style="list-style: none; padding: 0; margin: 0;">
            @foreach($disciplines as $discipline)
                <li style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <span>{{ $discipline->name }}</span>
                    <span>
                        <a href="{{ route('disciplines.edit', $discipline) }}">Edit</a>
                        &nbsp;·&nbsp;
                        <form action="{{ route('disciplines.destroy', $discipline) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this discipline?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background: none; border: none; padding: 0; color: #b91c1c; cursor: pointer; text-decoration: underline;">Delete</button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>
        <div style="margin-top: 16px;">{{ $disciplines->links() }}</div>
    </div>
@endif

@endsection
