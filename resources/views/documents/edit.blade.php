@extends('layouts.app')

@section('content')
    <h2>Replace document</h2>

    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="margin-bottom: 12px; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #b91c1c;">
            @foreach($errors->all() as $error)
                <p style="margin: 0 0 6px;">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <p style="margin: 0 0 8px;"><strong>File:</strong> {{ $document->file_name }}</p>
        <p style="margin: 0 0 8px;"><strong>Entity:</strong> {{ $document->entity?->name ?? '—' }}</p>
        <p style="margin: 0 0 8px;"><strong>Project:</strong> {{ $document->project?->project_number ?? '—' }} — {{ $document->project?->project_name ?? '—' }}</p>
        <p style="margin: 0;"><strong>Folder:</strong> {{ $document->display_folder }}</p>
    </div>

    @if(!$fileAvailable)
        <div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #b91c1c; margin-bottom: 16px;">
            The stored file is missing. You can still upload a replacement below; it will be saved to the same document record.
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0; font-size: 1rem;">How to edit and save</h3>
        <ol style="margin: 0; padding-left: 1.25rem; color: #334155; line-height: 1.6;">
            <li><strong>Download</strong> the current file (or open it in a new tab).</li>
            <li>Edit it on your computer (e.g. Adobe Acrobat, Word, Excel).</li>
            <li><strong>Upload the edited file</strong> below and click <strong>Save &amp; replace</strong>.</li>
        </ol>
        <p style="margin: 12px 0 0; font-size: 0.9rem; color: #64748b;">
            The same document record is kept (search, project, folder). Only the file in storage is overwritten. OCR runs again on the new file.
        </p>
        @if($fileAvailable)
            <p style="margin-top: 12px;">
                <a href="{{ route('documents.download', ['id' => $document->id]) }}">Download current file</a>
                &nbsp;|&nbsp;
                <a href="{{ route('documents.view', ['id' => $document->id]) }}" target="_blank" rel="noopener">Open in new tab</a>
            </p>
        @endif
    </div>

    @if($fileAvailable)
        <div class="card" style="margin-bottom: 16px; padding: 0; overflow: hidden;">
            <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <strong>Current file preview</strong>
            </div>
            <iframe
                src="{{ route('documents.view', ['id' => $document->id]) }}"
                title="Preview {{ $document->file_name }}"
                style="width: 100%; height: 70vh; border: 0; display: block;"
            ></iframe>
        </div>
    @endif

    <div class="card">
        <h3 style="margin-top: 0; font-size: 1rem;">Upload edited file</h3>
        <form method="POST" action="{{ route('documents.replace', ['id' => $document->id]) }}" enctype="multipart/form-data">
            @csrf
            @if(request()->has('return_url'))
                <input type="hidden" name="return_url" value="{{ request('return_url') }}">
            @endif
            <label for="replace_file" style="display: block; margin-bottom: 6px; font-weight: 500;">Edited file (PDF, Word, or Excel)</label>
            <input type="file" name="file" id="replace_file" accept=".pdf,.doc,.docx,.xls,.xlsx" required style="margin-bottom: 12px;">
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <button type="submit" onclick="return confirm('Replace the stored file with this upload? The previous file cannot be undone.');">
                    Save &amp; replace
                </button>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('documents.search') }}" style="color: #334155;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
