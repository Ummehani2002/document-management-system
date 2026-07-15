@php
    $normalizedRows = collect($rows)->map(function ($row) {
        if (is_array($row)) {
            return [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return [
            'label' => (string) ($row->label ?? ''),
            'total' => (int) ($row->total ?? 0),
        ];
    });
    $documentsTotal = (int) ($total ?? $normalizedRows->sum('total'));
    $downloadTab = $downloadTab ?? null;
    $totalLabel = $totalLabel ?? 'Total documents';
@endphp
<div class="dms-grid-wrap" style="margin-top: 0; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
    <div style="background:#212d3e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <h4 style="margin:0; font-size:1rem;">{{ $title }}</h4>
        @if($downloadTab)
            @include('summary-dashboard._download-button', ['tab' => $downloadTab, 'buttonClass' => 'dash-download-btn-light'])
        @endif
    </div>
    @if($normalizedRows->isEmpty())
        <p style="margin:0; padding:16px; color:#64748b;">No documents for the selected filters.</p>
    @else
        <table class="dms-grid-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="text-right">Documents</th>
                </tr>
            </thead>
            <tbody>
                @foreach($normalizedRows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right">{{ number_format($row['total']) }}</td>
                    </tr>
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
