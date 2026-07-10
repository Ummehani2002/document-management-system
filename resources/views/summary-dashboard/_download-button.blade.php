@php
    $downloadQuery = request()->only(['entity_id', 'project_id', 'main_folder', 'document_type', 'date_from', 'date_to']);
@endphp
<a
    href="{{ route('summary-dashboard.download', array_merge($downloadQuery, ['tab' => $tab])) }}"
    class="dash-download-btn"
>
    Download report (CSV)
</a>
