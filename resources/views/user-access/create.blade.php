@extends('layouts.app')

@section('content')

<h2>Add User</h2>

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
</p>

<form method="POST" action="{{ route('user-access.store') }}">
    @csrf

    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-top: 0;">User details</h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
            <div>
                <label for="name">Full name *</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required style="width:100%; padding:8px; margin-top:6px;">
            </div>
            <div>
                <label for="email">Work email *</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required style="width:100%; padding:8px; margin-top:6px;">
            </div>
        </div>
        <p style="color:#64748b; margin:12px 0 0;">They sign in with <strong>Microsoft</strong> using this email (no password needed).</p>
    </div>

    @include('user-access._access-form', [
        'user' => null,
        'roles' => $roles,
        'entities' => $entities,
        'folderTree' => $folderTree,
        'selectedEntityIds' => old('entity_ids', []),
        'selectedFolders' => old('folders', []),
        'selectedDocumentIds' => old('document_ids', []),
        'grantedDocuments' => collect(),
        'documentSearch' => '',
        'documentResults' => collect(),
        'formAction' => route('user-access.store'),
        'isEdit' => false,
    ])
</form>

@endsection
