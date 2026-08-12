@extends('layouts.app')

@section('content')
    <h2>Trash</h2>
    <p style="color: #64748b; margin-top: -8px;">Deleted PDFs stay here until you restore them or permanently delete them.</p>

    @if (session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="error" style="margin-bottom: 12px;">{{ $errors->first() }}</div>
    @endif

    <div class="card dms-grid-wrap">
        @if($documents->isEmpty())
            <p style="margin: 0; padding: 16px;">Trash is empty.</p>
        @else
            <table class="dms-grid-table min-w-xl">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Project Number</th>
                        <th>Project Name</th>
                        <th>Folder</th>
                        <th>Deleted At</th>
                        <th>File</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td style="word-break: break-word;">{{ $document->file_name }}</td>
                            <td>{{ $document->project?->project_number ?? '—' }}</td>
                            <td>{{ $document->project?->project_name ?? '—' }}</td>
                            <td>{{ $document->display_folder ?: ($document->document_type ?: '—') }}</td>
                            <td style="white-space: nowrap;">{{ format_model_datetime($document, 'deleted_at') }}</td>
                            <td>{{ $document->file_available ? 'Available' : 'Missing' }}</td>
                            <td class="text-right" style="white-space: nowrap;">
                                @if($document->can_restore)
                                    <form action="{{ route('documents.trash.restore', ['id' => $document->id]) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" style="background: none; border: none; padding: 0; color: #0f766e; cursor: pointer; text-decoration: underline;">Restore</button>
                                    </form>
                                    &nbsp;·&nbsp;
                                    <form action="{{ route('documents.trash.force-destroy', ['id' => $document->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this file? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background: none; border: none; padding: 0; color: #b91c1c; cursor: pointer; text-decoration: underline;">Delete forever</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding: 12px 16px;">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
@endsection
