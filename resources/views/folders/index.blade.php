@extends('layouts.app')

@section('content')

<h2>Folder Master</h2>
<p style="color:#64748b; margin-top:-6px; margin-bottom:18px;">
    Manage main categories and subfolders used in the sidebar, upload, and access controls.
</p>

@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="card" style="background:#fef2f2; border-color:#fecaca; margin-bottom:16px;">
        <ul style="margin:0; padding-left:20px; color:#b91c1c;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p style="margin-bottom:16px;">
    <a href="{{ route('folders.create') }}" style="display:inline-block; padding:10px 20px; background:#212d3e; color:#fff; text-decoration:none; border-radius:5px;">Add main folder</a>
</p>

@if($folders->isEmpty())
    <div class="card">
        <p>No folders yet. <a href="{{ route('folders.create') }}">Add a main folder</a>.</p>
    </div>
@else
    @foreach($folders as $folder)
        <div class="card" style="margin-bottom:16px; padding:0; overflow:hidden;">
            <div style="background:#212d3e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <strong>{{ $folder->name }}</strong>
                    <span style="opacity:0.8; font-size:0.85rem; margin-left:8px;">{{ $folder->subfolders->count() }} subfolder(s)</span>
                </div>
                <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                    <a href="{{ route('folders.subfolders.create', $folder) }}" style="color:#fff;">Add subfolder</a>
                    <a href="{{ route('folders.edit', $folder) }}" style="color:#fff;">Edit</a>
                    <form action="{{ route('folders.destroy', $folder) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this main folder?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" style="background:none; border:none; padding:0; color:#fca5a5; cursor:pointer; text-decoration:underline;">Delete</button>
                    </form>
                </div>
            </div>
            @if($folder->subfolders->isEmpty())
                <p style="margin:0; padding:14px 16px; color:#64748b;">No subfolders yet.</p>
            @else
                <div class="dms-grid-wrap">
                    <table class="dms-grid-table">
                        <thead>
                            <tr>
                                <th>Subfolder</th>
                                <th class="text-right">Sort</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($folder->subfolders as $sub)
                                <tr>
                                    <td>{{ $sub->name }}</td>
                                    <td class="text-right">{{ $sub->sort_order }}</td>
                                    <td class="text-right" style="white-space:nowrap;">
                                        <a href="{{ route('folders.subfolders.edit', $sub) }}">Edit</a>
                                        &nbsp;·&nbsp;
                                        <form action="{{ route('folders.subfolders.destroy', $sub) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this subfolder?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background:none; border:none; padding:0; color:#b91c1c; cursor:pointer; text-decoration:underline;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
@endif

@endsection
