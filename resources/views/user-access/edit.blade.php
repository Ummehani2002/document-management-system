@extends('layouts.app')

@section('content')

<h2>Manage Access — {{ $user->name }}</h2>

@if ($errors->any())
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;">
        <ul style="margin: 0; padding-left: 18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p style="margin-bottom: 20px;">
    <a href="{{ route('user-access.index') }}">&larr; Back to user list</a>
    &nbsp;·&nbsp; {{ $user->email }}
</p>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Find documents to grant</h3>
    <form method="GET" action="{{ route('user-access.edit', $user) }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom: 12px;">
        <input type="search" name="doc_q" value="{{ $documentSearch }}" placeholder="Search by file name…" style="padding:8px; min-width:260px;">
        <button type="submit">Search</button>
        @if($documentSearch !== '')
            <a href="{{ route('user-access.edit', $user) }}">Clear search</a>
        @endif
    </form>
    @if($documentSearch !== '' && $documentResults->isEmpty())
        <p style="color:#64748b; margin:0;">No documents match "{{ $documentSearch }}".</p>
    @endif
</div>

<form method="POST" action="{{ route('user-access.update', $user) }}">
    @csrf
    @method('PUT')

    @include('user-access._access-form', [
        'user' => $user,
        'roles' => $roles,
        'entities' => $entities,
        'folderTree' => $folderTree,
        'selectedEntityIds' => $selectedEntityIds,
        'selectedFolders' => $selectedFolders,
        'selectedDocumentIds' => $selectedDocumentIds,
        'grantedDocuments' => $grantedDocuments,
        'documentSearch' => $documentSearch,
        'documentResults' => $documentResults,
        'isEdit' => true,
    ])
</form>

@endsection
