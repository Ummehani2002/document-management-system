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
        @if(!empty($fileSizeMb))
            <p style="margin: 8px 0 0; font-size: 0.9rem; color: #64748b;">Size: {{ $fileSizeMb }} MB</p>
        @endif
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
                @if(!empty($previewUrl))
                    &nbsp;|&nbsp;
                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener">Open in new tab</a>
                @endif
            </p>
        @endif
    </div>

    @if($fileAvailable && !empty($previewUrl))
        <div class="card" style="margin-bottom: 16px; padding: 0; overflow: hidden;">
            <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between;">
                <strong>Current file preview</strong>
                <button type="button" id="load-preview-btn" style="margin: 0;">Show preview</button>
            </div>
            <p id="preview-hint" style="margin: 0; padding: 12px 14px; font-size: 0.9rem; color: #64748b;">
                Preview loads on demand from cloud storage (faster than before). Large PDFs may still take a few seconds to render.
                @if(!empty($fileSizeMb) && $fileSizeMb > 25)
                    This file is {{ $fileSizeMb }} MB — use <strong>Download</strong> if preview is slow.
                @endif
            </p>
            <p id="preview-loading" style="display: none; margin: 0; padding: 8px 14px; color: #334155;">Loading preview…</p>
            <iframe
                id="preview-frame"
                src="about:blank"
                data-src="{{ $previewUrl }}"
                title="Preview {{ $document->file_name }}"
                style="width: 100%; height: 70vh; border: 0; display: none;"
            ></iframe>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('load-preview-btn');
            var frame = document.getElementById('preview-frame');
            var hint = document.getElementById('preview-hint');
            var loading = document.getElementById('preview-loading');
            if (!btn || !frame) return;

            var loaded = false;
            var visible = false;

            btn.addEventListener('click', function () {
                if (loaded) {
                    visible = !visible;
                    frame.style.display = visible ? 'block' : 'none';
                    btn.textContent = visible ? 'Hide preview' : 'Show preview';
                    return;
                }

                var url = frame.getAttribute('data-src');
                if (!url) return;

                loading.style.display = 'block';
                hint.style.display = 'none';
                btn.disabled = true;
                btn.textContent = 'Loading…';

                frame.onload = function () {
                    loading.style.display = 'none';
                    frame.style.display = 'block';
                    btn.textContent = 'Hide preview';
                    btn.disabled = false;
                    loaded = true;
                    visible = true;
                };
                frame.onerror = function () {
                    loading.style.display = 'none';
                    hint.style.display = 'block';
                    hint.textContent = 'Preview could not load. Use Download or Open in new tab.';
                    btn.disabled = false;
                    btn.textContent = 'Retry preview';
                    loaded = false;
                };
                frame.src = url;
            });
        });
        </script>
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
