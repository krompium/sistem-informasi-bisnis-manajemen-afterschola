<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 18px; margin: 0; }
        .kop .sub { font-size: 11px; margin-top: 2px; }
        table.info { width: 100%; border-collapse: collapse; }
        table.info td { padding: 4px 0; vertical-align: top; }
        table.info td.k { width: 130px; color: #555; }
        .amount { margin: 16px 0; padding: 10px 14px; border: 1px solid #111; font-size: 16px; font-weight: bold; }
        .foot { margin-top: 18px; color: #555; font-size: 10px; }
    </style>
</head>
<body>
    @php($registration = $invoice->registration)
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">KUITANSI PEMBAYARAN{{ $registration->branch ? ' — '.$registration->branch->name : '' }}</div>
    </div>

    <table class="info">
        <tr><td class="k">No. Kuitansi</td><td>{{ $invoice->number }}</td></tr>
        <tr><td class="k">Telah diterima dari</td><td>{{ $registration->parent_name }}</td></tr>
        <tr><td class="k">Untuk siswa</td><td>{{ $registration->student_name }}</td></tr>
        <tr><td class="k">Pembayaran program</td><td>{{ $registration->program?->name ?? '-' }}</td></tr>
        <tr><td class="k">Metode</td><td>{{ ($payment?->method ?? '') === 'transfer' ? 'Transfer' : 'Tunai' }}</td></tr>
        <tr><td class="k">Tanggal lunas</td><td>{{ $invoice->paid_at?->copy()->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td></tr>
    </table>

    <div class="amount">Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}</div>

    <div class="foot">Dokumen ini diterbitkan otomatis oleh sistem After Schola dan sah tanpa tanda tangan.</div>
</body>
</html>
