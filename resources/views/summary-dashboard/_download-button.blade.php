@php
    $buttonClass = trim('dash-download-btn '.($buttonClass ?? ''));
@endphp
<button
    type="button"
    class="{{ $buttonClass }}"
    data-pdf-tab="{{ $tab }}"
>
    Download report (PDF)
</button>
