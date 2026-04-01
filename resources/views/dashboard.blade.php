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
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="text-align:left; padding:10px 12px;">File</th>
                        <th style="text-align:left; padding:10px 12px;">Entity</th>
                        <th style="text-align:left; padding:10px 12px;">Project</th>
                        <th style="text-align:left; padding:10px 12px;">Folder</th>
                        <th style="text-align:right; padding:10px 12px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentDocuments as $doc)
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:10px 12px; min-width:0;">
                            <strong>{{ $doc->file_name }}</strong><br>
                            <span style="font-size: 0.85rem; color: #64748b;">
                                {{ optional($doc->created_at)->format('d M Y, h:i A') ?: '-' }}
                            </span>
                        </td>
                        <td style="padding:10px 12px;">{{ $doc->entity?->name ?? '-' }}</td>
                        <td style="padding:10px 12px;">{{ $doc->project?->project_number ?? '-' }}</td>
                        <td style="padding:10px 12px;">{{ $doc->document_type ?? '-' }}</td>
                        <td style="padding:10px 12px; text-align:right; white-space:nowrap;">
                            <a href="{{ route('documents.download', ['id' => $doc->id], false) }}">Download</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
