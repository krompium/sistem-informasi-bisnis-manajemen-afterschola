<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #0B2A5B; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 16px; margin: 0; color: #0B2A5B; }
        .kop .sub { font-size: 11px; margin-top: 2px; color: #555; }
        h2 { font-size: 13px; margin: 0 0 2px; color: #0B2A5B; }
        .meta { color: #555; margin: 0 0 8px; }
        table.sum { border-collapse: collapse; margin-bottom: 10px; }
        table.sum td { border: 1px solid #cbd5e1; padding: 4px 10px; text-align: center; }
        table.sum .k { color: #555; font-size: 9px; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { background: #92D050; border: 1px solid #94a3b8; padding: 4px 5px; text-align: left; }
        table.t td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: top; word-break: break-word; }
        .muted { color: #888; font-style: italic; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Laporan Progres Proyek</div>
    </div>

    <h2>{{ $report['name'] }}</h2>
    <p class="meta">Team: {{ $report['team'] }} &bull; Status: {{ $report['status'] }} &bull; {{ $report['period'] }} &bull; dicetak {{ now()->format('d/m/Y H:i') }}</p>

    <table class="sum">
        <tr>
            <td><div class="k">Total task</div>{{ $report['summary']['total'] }}</td>
            <td><div class="k">Rata-rata progres</div>{{ $report['summary']['avg_progress'] }}%</td>
            <td><div class="k">Terlambat</div>{{ $report['summary']['overdue'] }}</td>
            @foreach ($report['summary']['by_status'] as $label => $count)
                <td><div class="k">{{ $label }}</div>{{ $count }}</td>
            @endforeach
        </tr>
    </table>

    <table class="t">
        <thead>
            <tr>
                @foreach ($header as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($header) }}" class="muted">Belum ada task.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
