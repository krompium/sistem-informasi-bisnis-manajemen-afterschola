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
        h2 { font-size: 14px; text-align: center; margin: 0 0 12px; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows td { padding: 4px 2px; vertical-align: top; }
        table.rows td.label { width: 32%; color: #555; }
        .amount { margin-top: 14px; padding: 8px; border: 1px solid #111; font-size: 14px; font-weight: bold; }
        .lunas { margin-top: 14px; color: #0a7d24; font-weight: bold; font-size: 13px; }
        .muted { color: #666; font-size: 9px; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        @if ($branch)
            <div class="sub">{{ $branch->name }}</div>
        @endif
    </div>

    <h2>KUITANSI PEMBAYARAN</h2>

    <table class="rows">
        <tr><td class="label">Nomor invoice</td><td>{{ $invoice->number }}</td></tr>
        <tr><td class="label">Telah diterima dari</td><td>{{ $registration->parent_name }}</td></tr>
        <tr><td class="label">Nama siswa</td><td>{{ $registration->student_name }}</td></tr>
        <tr><td class="label">Program</td><td>{{ $registration->program?->name ?? '-' }}</td></tr>
        <tr><td class="label">Metode pembayaran</td><td>{{ $payment?->method === 'tunai' ? 'Tunai' : 'Transfer' }}</td></tr>
        <tr><td class="label">Tanggal lunas</td><td>{{ $invoice->paid_at?->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td></tr>
    </table>

    <div class="amount">Jumlah: Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}</div>
    <div class="lunas">LUNAS</div>

    <div class="muted">Kuitansi ini dibuat otomatis oleh sistem After Schola dan sah tanpa tanda tangan.</div>
</body>
</html>
