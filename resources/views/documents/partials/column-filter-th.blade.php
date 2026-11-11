@php
    $filterValue = trim((string) request($filterKey, ''));
    $isActive = $filterValue !== '';
@endphp
<th class="dms-col-filterable">
    <div class="dms-col-header">
        <span>{{ $label }}</span>
        <button
            type="button"
            class="dms-col-filter-btn{{ $isActive ? ' is-active' : '' }}"
            data-filter-toggle="{{ $filterKey }}"
            aria-label="Filter {{ $label }}"
            aria-expanded="false"
            aria-haspopup="true"
        >
            <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M1.5 2.5h13l-4.75 5.5v4.25L6.25 14V8L1.5 2.5z"/>
            </svg>
        </button>
    </div>
    <div class="dms-col-filter-menu" data-filter-menu="{{ $filterKey }}" role="dialog" aria-label="Filter {{ $label }}">
        <label for="dms-filter-input-{{ $filterKey }}">Show rows where {{ strtolower($label) }} contains</label>
        <input
            id="dms-filter-input-{{ $filterKey }}"
            type="search"
            name="{{ $filterKey }}"
            value="{{ $filterValue }}"
            placeholder="Enter text"
            autocomplete="off"
        >
        <div class="dms-col-filter-actions">
            <button type="button" class="dms-filter-clear" data-filter-clear="{{ $filterKey }}">Clear</button>
            <button type="button" class="dms-filter-apply" data-filter-apply="{{ $filterKey }}">Apply</button>
        </div>
    </div>
</th>
