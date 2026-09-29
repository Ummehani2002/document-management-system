@extends('layouts.app')

@section('content')
    <h2>Open Office file</h2>

    <div class="card" style="margin-bottom: 16px; padding: 16px;">
        <p style="margin: 0 0 6px;"><strong>{{ $document->file_name }}</strong></p>
        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
            {{ $document->entity?->name ?? '—' }} · {{ $document->project?->project_number ?? '—' }} · {{ $document->display_folder }}
        </p>
    </div>

    <div class="card" style="margin-bottom: 16px; padding: 16px; background: #fff7ed; border: 1px solid #fed7aa;">
        <p style="margin: 0 0 8px; color: #9a3412; font-weight: 600;">Do not use “Edit a copy” in Microsoft Office Online</p>
        <p style="margin: 0; color: #9a3412; line-height: 1.55; font-size: 0.92rem;">
            Opening Excel/Word in the browser’s Office Online viewer only saves to OneDrive/SharePoint.
            Those changes <strong>never</strong> update the DMS. To change the file in the portal, use
            <strong>Edit in DMS</strong> below (or Download → edit → upload).
        </p>
    </div>

    <div class="card" style="padding: 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
        <a href="{{ $editUrl }}" class="btn-primary" style="padding: 12px 18px; text-decoration: none; display: inline-block;">Edit in DMS</a>
        <a href="{{ $downloadUrl }}" style="padding: 12px 18px; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; display: inline-block;">Download</a>
        <a href="{{ route('documents.search') }}" style="color: #64748b; font-size: 0.9rem;">Back to search</a>
    </div>

    @if(!empty($readOnlyViewerUrl))
        <div class="card" style="margin-top: 16px; padding: 0; overflow: hidden;">
            <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 0.88rem;">
                Read-only preview (viewing only — edits here will not save to DMS)
            </div>
            <iframe
                src="{{ $readOnlyViewerUrl }}"
                title="Read-only preview of {{ $document->file_name }}"
                style="width: 100%; height: calc(100vh - 320px); min-height: 480px; border: 0;"
            ></iframe>
        </div>
    @endif
@endsection
