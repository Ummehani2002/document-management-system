@php
    $groups = collect($rows ?? [])->values();
    $documentsTotal = (int) ($total ?? $groups->sum('total'));
    $downloadTab = $downloadTab ?? null;
    $totalLabel = $totalLabel ?? 'Total documents';
@endphp
<div class="dms-grid-wrap" style="margin-top: 0; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
    <div style="background:#f8fafc; color:#1e293b; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; border-bottom:1px solid #e2e8f0;">
        <h4 style="margin:0; font-size:1rem;">{{ $title }}</h4>
        @if($downloadTab)
            @include('summary-dashboard._download-button', ['tab' => $downloadTab, 'buttonClass' => 'dash-download-btn-light'])
        @endif
    </div>
    @if($groups->isEmpty())
        <p style="margin:0; padding:16px; color:#64748b;">No documents for the selected filters.</p>
    @else
        <table class="dms-grid-table folder-hierarchy-table" id="folderHierarchyTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="text-right">Documents</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groups as $index => $group)
                    <tr class="folder-parent-row" data-hierarchy-toggle="{{ $index }}" role="button" tabindex="0" aria-expanded="false">
                        <td>
                            <span class="folder-caret" aria-hidden="true">&#9654;</span>
                            <strong>{{ $group['label'] }}</strong>
                            <span class="folder-child-count">{{ count($group['children'] ?? []) }} folder{{ count($group['children'] ?? []) === 1 ? '' : 's' }}</span>
                        </td>
                        <td class="text-right"><strong>{{ number_format((int) $group['total']) }}</strong></td>
                    </tr>
                    @foreach(($group['children'] ?? []) as $child)
                        <tr class="folder-child-row is-collapsed" data-hierarchy-parent="{{ $index }}">
                            <td class="folder-child-name">{{ $child['label'] }}</td>
                            <td class="text-right">{{ number_format((int) $child['total']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc; font-weight:600;">
                    <td>{{ $totalLabel }}</td>
                    <td class="text-right">{{ number_format($documentsTotal) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>

<style>
    .folder-hierarchy-table .folder-parent-row {
        cursor: pointer;
        background: #fff;
        user-select: none;
    }
    .folder-hierarchy-table .folder-parent-row:hover {
        background: #f8fafc;
    }
    .folder-hierarchy-table .folder-parent-row.is-open {
        background: #f1f5f9;
    }
    .folder-hierarchy-table .folder-caret {
        display: inline-block;
        width: 1em;
        margin-right: 8px;
        color: #64748b;
        transition: transform 0.15s ease;
        font-size: 0.75rem;
    }
    .folder-hierarchy-table .folder-parent-row.is-open .folder-caret {
        transform: rotate(90deg);
    }
    .folder-hierarchy-table .folder-child-count {
        margin-left: 8px;
        color: #94a3b8;
        font-size: 0.82rem;
        font-weight: 400;
    }
    .folder-hierarchy-table .folder-child-row td {
        background: #fafbfc;
        color: #334155;
    }
    .folder-hierarchy-table .folder-child-name {
        padding-left: 36px !important;
    }
    .folder-hierarchy-table .folder-child-row.is-collapsed {
        display: none;
    }
</style>

<script>
    (function () {
        function bindHierarchyToggles(root) {
            root.querySelectorAll('.folder-parent-row[data-hierarchy-toggle]').forEach(function (row) {
                if (row.dataset.hierarchyBound === '1') {
                    return;
                }
                row.dataset.hierarchyBound = '1';

                function toggle(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var index = row.getAttribute('data-hierarchy-toggle');
                    var open = !row.classList.contains('is-open');
                    row.classList.toggle('is-open', open);
                    row.setAttribute('aria-expanded', open ? 'true' : 'false');

                    root.querySelectorAll('.folder-child-row[data-hierarchy-parent="' + index + '"]').forEach(function (child) {
                        child.classList.toggle('is-collapsed', !open);
                    });
                }

                row.addEventListener('click', toggle);
                row.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        toggle(event);
                    }
                });
            });
        }

        function init() {
            var table = document.getElementById('folderHierarchyTable');
            if (table) {
                bindHierarchyToggles(table);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
