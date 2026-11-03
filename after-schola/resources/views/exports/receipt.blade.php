<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #0B2A5B; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 16px; margin: 0; color: #0B2A5B; }
        .kop .sub { font-size: 11px; margin-top: 2px; color: #555; }
        table.f { width: 100%; border-collapse: collapse; }
        table.f td { padding: 4px; vertical-align: top; }
        table.f td.k { width: 150px; color: #555; }
        .total { margin-top: 14px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; font-size: 14px; font-weight: bold; color: #0B2A5B; }
        .foot { margin-top: 18px; color: #888; font-size: 10px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>After Schola</h1>
        <div class="sub">Kuitansi Pembayaran{{ $invoice->registration->branch ? ' — '.$invoice->registration->branch->name : '' }}</div>
    </div>

    <table class="f">
        <tr><td class="k">Nomor</td><td>{{ $invoice->number }}</td></tr>
        <tr><td class="k">Diterima dari</td><td>{{ $invoice->registration->parent_name }}</td></tr>
        <tr><td class="k">Nama siswa</td><td>{{ $invoice->registration->student_name }}</td></tr>
        <tr><td class="k">Untuk pembayaran</td><td>{{ $invoice->registration->program?->name }}</td></tr>
        <tr><td class="k">Tanggal pembayaran</td><td>{{ ($payment?->paid_date ?? $invoice->paid_at)?->format('d/m/Y') }}</td></tr>
        <tr><td class="k">Metode</td><td>{{ $payment ? ucfirst($payment->method) : '-' }}</td></tr>
    </table>

    <div class="total">Jumlah: Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }} — LUNAS</div>
    <div class="foot">Dicetak {{ now()->format('d/m/Y H:i') }}. Kuitansi ini sah tanpa tanda tangan.</div>
</body>
</html>
