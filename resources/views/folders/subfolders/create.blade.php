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
        <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. Service Agreement" required>

        <label for="auto_keywords" style="margin-top:12px;">Auto-detect keywords</label>
        <textarea name="auto_keywords" id="auto_keywords" rows="3" placeholder="e.g. Service Agreement, SA, SVC-AGR" style="width:100%; padding:10px;">{{ old('auto_keywords') }}</textarea>
        <p style="margin:8px 0 0; color:#64748b; font-size:0.9rem;">
            Used by <strong>Auto upload</strong>. Comma-separated words/phrases found in the filename or OCR text.
            Leave blank to use the subfolder name only.
        </p>

        <label for="sort_order" style="margin-top:12px;">Sort order</label>
        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0">
    </div>
    <div style="margin-top:20px;">
        <button type="submit">Save</button>
        <a href="{{ route('folders.index') }}" style="margin-left:12px; color:#64748b;">Cancel</a>
    </div>
</form>

@endsection
