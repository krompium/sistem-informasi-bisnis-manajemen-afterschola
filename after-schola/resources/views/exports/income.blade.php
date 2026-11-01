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
        tr.total td { background: #f4f4f4; font-weight: bold; }
        .muted { color: #666; }
    </style>
</head>
<body>
    @php($rupiah = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.'))
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Laporan Pemasukan</div>
        <div class="sub muted">Periode: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</div>
    </div>

    <h2>Ringkasan</h2>
    <table>
        <tr class="total"><td>Total pemasukan ({{ $summary['count'] }} pembayaran)</td><td class="r">{{ $rupiah($summary['total']) }}</td></tr>
    </table>

    <h2>Per cabang</h2>
    <table>
        <thead><tr><th>Cabang</th><th class="r">Pembayaran</th><th class="r">Jumlah</th></tr></thead>
        @forelse ($summary['by_branch'] as $item)
            <tr><td>{{ $item['branch_name'] }}</td><td class="r">{{ $item['count'] }}</td><td class="r">{{ $rupiah($item['total']) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">Tidak ada pemasukan pada periode ini.</td></tr>
        @endforelse
    </table>

    <h2>Per program</h2>
    <table>
        <thead><tr><th>Program</th><th class="r">Pembayaran</th><th class="r">Jumlah</th></tr></thead>
        @forelse ($summary['by_program'] as $item)
            <tr><td>{{ $item['program_name'] }}</td><td class="r">{{ $item['count'] }}</td><td class="r">{{ $rupiah($item['total']) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">Tidak ada pemasukan pada periode ini.</td></tr>
        @endforelse
    </table>

    <h2>Rincian pembayaran</h2>
    <table>
        <thead>
            <tr><th>Tanggal</th><th>Invoice</th><th>Siswa</th><th>Program</th><th>Cabang</th><th class="r">Jumlah</th></tr>
        </thead>
        @foreach ($summary['rows'] as $row)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($row['confirmed_at'])->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td>
                <td>{{ $row['invoice_number'] }}</td>
                <td>{{ $row['student_name'] }}</td>
                <td>{{ $row['program_name'] ?? '-' }}</td>
                <td>{{ $row['branch_name'] ?? '-' }}</td>
                <td class="r">{{ $rupiah($row['amount']) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
