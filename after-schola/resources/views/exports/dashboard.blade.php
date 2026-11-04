<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 16px; margin: 0; }
        .kop .sub { font-size: 11px; margin-top: 2px; }
        h2 { font-size: 12px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #666; padding: 3px 4px; }
        th { background: #eee; font-size: 9px; text-align: left; }
        td.r, th.r { text-align: right; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Laporan Dashboard Manajemen</div>
        <div class="sub muted">Periode: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · {{ $branch }}</div>
    </div>

    @foreach ($sections as $title => $rows)
        <h2>{{ $title }}</h2>
        <table>
            <tr><th>Indikator</th><th class="r">Nilai</th><th>Keterangan</th></tr>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="r">{{ $row['display'] }}</td>
                    <td class="muted">{{ $row['note'] }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>
