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
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #666; padding: 3px 4px; }
        th { background: #eee; font-size: 9px; }
        td.r { text-align: right; }
        tr.total td { background: #f4f4f4; font-weight: bold; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Laporan Pemasukan — {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') }}</div>
    </div>

    <table>
        <thead>
            <tr><th>TANGGAL</th><th>NO. INVOICE</th><th>SISWA</th><th>PROGRAM</th><th>CABANG</th><th>METODE</th><th>JUMLAH</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach (array_slice($row, 0, 6) as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                    <td class="r">{{ number_format($row[6], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center">Tidak ada pemasukan pada periode ini.</td></tr>
            @endforelse
            <tr class="total"><td colspan="6" class="r">TOTAL</td><td class="r">{{ number_format($total, 0, ',', '.') }}</td></tr>
        </tbody>
    </table>
</body>
</html>
