@extends('layouts.app')

@section('content')

@php
    $mains = \App\Models\DocumentMainFolder::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
@endphp

<h2>Edit subfolder</h2>
<p style="color:#64748b; margin-bottom:20px;"><a href="{{ route('folders.index') }}">← Back to Folder Master</a></p>

@if($errors->any())
    <div class="card" style="background:#fef2f2; border-color:#fecaca;">
        <ul style="margin:0; padding-left:20px; color:#b91c1c;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('folders.subfolders.update', $subfolder) }}">
    @csrf
    @method('PUT')
    <div class="card">
        <label for="main_folder_id">Main folder *</label>
        <select name="main_folder_id" id="main_folder_id" required>
            @foreach($mains as $main)
                <option value="{{ $main->id }}" @selected((int) old('main_folder_id', $subfolder->main_folder_id) === (int) $main->id)>
                    {{ $main->name }}
                </option>
            @endforeach
        </select>

        <label for="name" style="margin-top:12px;">Subfolder name *</label>
        <input type="text" name="name" id="name" value="{{ old('name', $subfolder->name) }}" required>

        <label for="sort_order" style="margin-top:12px;">Sort order</label>
        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $subfolder->sort_order) }}" min="0">

        <p style="margin:10px 0 0; color:#64748b; font-size:0.9rem;">
            Renaming updates existing documents and access rules that use this subfolder name.
        </p>
    </div>
    <div style="margin-top:20px;">
        <button type="submit">Save</button>
        <a href="{{ route('folders.index') }}" style="margin-left:12px; color:#64748b;">Cancel</a>
    </div>
</form>

@endsection
