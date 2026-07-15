@extends('layouts.app')

@section('content')

<h2>Add subfolder</h2>
<p style="color:#64748b; margin-bottom:20px;">
    Under <strong>{{ $folder->name }}</strong> ·
    <a href="{{ route('folders.index') }}">← Back to Folder Master</a>
</p>

@if($errors->any())
    <div class="card" style="background:#fef2f2; border-color:#fecaca;">
        <ul style="margin:0; padding-left:20px; color:#b91c1c;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('folders.subfolders.store', $folder) }}">
    @csrf
    <div class="card">
        <label for="name">Subfolder name *</label>
        <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. Shop Drawing" required>

        <label for="sort_order" style="margin-top:12px;">Sort order</label>
        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0">
    </div>
    <div style="margin-top:20px;">
        <button type="submit">Save</button>
        <a href="{{ route('folders.index') }}" style="margin-left:12px; color:#64748b;">Cancel</a>
    </div>
</form>

@endsection
