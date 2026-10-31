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
        h2 { font-size: 12px; color: #0B2A5B; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0B2A5B; color: #fff; text-align: left; padding: 4px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 3px 4px; }
        .r { text-align: right; }
        .total { font-weight: bold; background: #f1f5f9; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Laporan Pemasukan, {{ $report->from->format('d/m/Y') }} s.d. {{ $report->to->format('d/m/Y') }}</div>
    </div>

    <h2>Ringkasan: Rp {{ number_format($summary['total'], 0, ',', '.') }} dari {{ $summary['count'] }} pembayaran</h2>

    @foreach (['by_program' => ['Per Program', 'program'], 'by_branch' => ['Per Cabang', 'branch']] as $key => [$title, $field])
        <h2>{{ $title }}</h2>
        <table>
            <tr><th>{{ $title === 'Per Program' ? 'Program' : 'Cabang' }}</th><th class="r">Jumlah</th><th class="r">Pemasukan (Rp)</th></tr>
            @forelse ($summary[$key] as $row)
                <tr><td>{{ $row[$field] }}</td><td class="r">{{ $row['count'] }}</td><td class="r">{{ number_format($row['total'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="3">Tidak ada pemasukan pada periode ini.</td></tr>
            @endforelse
        </table>
    @endforeach

    <h2>Rincian</h2>
    <table>
        <tr><th>Tanggal</th><th>Invoice</th><th>Siswa</th><th>Program</th><th>Cabang</th><th class="r">Nominal (Rp)</th></tr>
        @foreach ($payments as $payment)
            @php($registration = $payment->invoice->registration)
            <tr>
                <td>{{ $payment->confirmed_at->copy()->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td>
                <td>{{ $payment->invoice->number }}</td>
                <td>{{ $registration->student_name }}</td>
                <td>{{ $registration->program?->name }}</td>
                <td>{{ $registration->branch?->name }}</td>
                <td class="r">{{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="5">Total</td><td class="r">{{ number_format($summary['total'], 0, ',', '.') }}</td></tr>
    </table>
</body>
</html>
