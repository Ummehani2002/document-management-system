@extends('layouts.app')

@section('content')
    <h2>Home</h2>

    <div class="card" style="padding: 18px 20px; margin-bottom: 18px;">
        <form method="GET" action="{{ route('dashboard') }}" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div style="min-width: 260px;">
                <label for="entity_id" style="margin-bottom:6px;">Entity</label>
                <select id="entity_id" name="entity_id" style="margin:0;">
                    <option value="">All entities</option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" {{ (int) $selectedEntityId === (int) $entity->id ? 'selected' : '' }}>
                            {{ $entity->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Apply</button>
        </form>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <div style="background:#212d3e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.05rem;">Recent uploads</h3>
            <span style="font-size:0.92rem; opacity:0.95;">Total: {{ $recentDocuments->count() }}</span>
        </div>
        @if($recentDocuments->isEmpty())
            <p style="margin: 0; padding: 16px;">No documents yet. <a href="{{ route('documents.upload') }}">Upload PDFs</a>.</p>
        @else
            <div class="dms-grid-wrap">
                <table class="dms-grid-table min-w-lg">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Entity</th>
                            <th>Project</th>
                            <th>Folder</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($recentDocuments as $doc)
                        <tr>
                            <td style="min-width:0;">
                                <strong>{{ $doc->file_name }}</strong><br>
                                <span style="font-size: 0.85rem; color: #64748b;">
                                    {{ format_model_datetime($doc, 'created_at') }}
                                </span>
                            </td>
                            <td>{{ $doc->entity?->name ?? '-' }}</td>
                            <td>{{ $doc->project?->project_number ?? '-' }}</td>
                            <td>{{ $doc->display_folder }}</td>
                            <td class="text-right" style="white-space:nowrap;">
                                @if(!empty($doc->file_available))
                                    <a href="{{ route('documents.download', ['id' => $doc->id]) }}">Download</a>
                                    <span style="color: #cbd5e1;"> | </span>
                                    <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this file?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="padding: 0; background: none; border: none; color: #b91c1c; text-decoration: underline; cursor: pointer;">Delete</button>
                                    </form>
                                @else
                                    <span style="color:#b91c1c; font-size:0.85rem; margin-right:8px;">File missing</span>
                                    <form action="{{ route('documents.destroy', ['id' => $doc->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record? File is already missing from storage.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="padding: 0; background: none; border: none; color: #b91c1c; text-decoration: underline; cursor: pointer;">Delete record</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
