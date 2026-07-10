<div class="dms-grid-wrap" style="margin-top: 20px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
    <div style="background:#212d3e; color:#fff; padding:12px 16px;">
        <h4 style="margin:0; font-size:1rem;">{{ $title }}</h4>
    </div>
    @if($rows->isEmpty())
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
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right">{{ number_format($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
