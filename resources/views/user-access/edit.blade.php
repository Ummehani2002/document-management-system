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
</p>

<form method="POST" action="{{ route('user-access.update', $user) }}">
    @csrf
    @method('PUT')

    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-top: 0;">Role</h3>
        <label>
            <select name="role" style="min-width: 220px; padding: 8px;">
                <option value="">— No role —</option>
                @foreach ($roles as $roleName)
                    <option value="{{ $roleName }}" @selected($user->hasRole($roleName))>{{ $roleName }}</option>
                @endforeach
            </select>
        </label>
        <p style="color: #64748b; margin: 8px 0 0;">Admin users bypass entity and folder restrictions.</p>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">Entity &amp; folder access</h3>
        <p style="color: #64748b;">
            Check an entity to grant access. Optionally restrict folders below — leave all folders unchecked to allow every folder in that entity.
        </p>

        @if($entities->isEmpty())
            <p>No entities yet. <a href="{{ route('entities.create') }}">Create an entity</a> first.</p>
        @else
            @foreach ($entities as $entity)
                @php
                    $entityChecked = in_array($entity->id, $selectedEntityIds, true);
                    $entityFolderKeys = $selectedFolders[$entity->id] ?? [];
                @endphp
                <div class="access-entity-block" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="entity_ids[]"
                            value="{{ $entity->id }}"
                            class="entity-toggle"
                            data-entity="{{ $entity->id }}"
                            @checked($entityChecked)
                        >
                        <strong>{{ $entity->name }}</strong>
                    </label>

                    <div class="entity-folders" data-entity-folders="{{ $entity->id }}" style="margin-top: 14px; @if(!$entityChecked) display: none; @endif">
                        @foreach ($folderTree as $mainFolder => $subfolders)
                            <div style="margin-bottom: 12px;">
                                <div style="font-weight: 500; margin-bottom: 6px;">{{ $mainFolder }}</div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px 12px; padding-left: 8px;">
                                    @foreach ($subfolders as $sub)
                                        @php $key = $mainFolder.'|'.$sub; @endphp
                                        <label style="display: flex; align-items: flex-start; gap: 6px; cursor: pointer;">
                                            <input
                                                type="checkbox"
                                                name="folders[{{ $entity->id }}][]"
                                                value="{{ $key }}"
                                                class="folder-check"
                                                data-entity="{{ $entity->id }}"
                                                @checked(in_array($key, $entityFolderKeys, true))
                                            >
                                            <span>{{ $sub }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <p style="margin-top: 20px;">
        <button type="submit" style="padding: 10px 24px; background: #212d3e; color: #fff; border: none; border-radius: 5px; cursor: pointer;">
            Save access
        </button>
    </p>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.entity-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var entityId = toggle.getAttribute('data-entity');
                var panel = document.querySelector('[data-entity-folders="' + entityId + '"]');
                if (!panel) return;
                panel.style.display = toggle.checked ? '' : 'none';
                if (!toggle.checked) {
                    panel.querySelectorAll('.folder-check').forEach(function (cb) {
                        cb.checked = false;
                    });
                }
            });
        });
    });
</script>

@endsection
