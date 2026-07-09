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

<form method="POST" action="{{ route('user-access.update', $user) }}">
    @csrf
    @method('PUT')

    @include('user-access._access-form', [
        'user' => $user,
        'roles' => $roles,
        'entities' => $entities,
        'folderTree' => $folderTree,
        'selectedProjectIds' => $selectedProjectIds,
        'selectedFoldersByProject' => $selectedFoldersByProject,
        'selectedDocumentIds' => $selectedDocumentIds,
        'grantedDocuments' => $grantedDocuments,
        'isEdit' => true,
    ])
</form>

@endsection
