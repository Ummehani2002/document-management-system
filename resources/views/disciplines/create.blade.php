@extends('layouts.app')

@section('content')

<h2>Add Discipline</h2>
<p style="color: #64748b; margin-bottom: 20px;"><a href="{{ route('disciplines.index') }}">← Back to Discipline Master</a></p>

@if($errors->any())
    <div class="card" style="background: #fef2f2; border-color: #fecaca;">
        <ul style="margin: 0; padding-left: 20px; color: #b91c1c;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('disciplines.store') }}">
    @csrf

    <div class="card">
        <label for="name">Discipline name *</label>
        <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. Mechanical, Electrical" required>
    </div>

    <div style="margin-top: 20px;">
        <button type="submit">Save</button>
        <a href="{{ route('disciplines.index') }}" style="margin-left: 12px; color: #64748b;">Cancel</a>
    </div>
</form>

@endsection
