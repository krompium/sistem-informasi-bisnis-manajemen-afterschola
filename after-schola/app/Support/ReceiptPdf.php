<?php

namespace App\Support;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

/**
 * Kuitansi PDF untuk invoice Lunas (KEU-03, ORT-09). Dipakai Keuangan dan portal orang tua; otorisasi di controller.
 * TODO(nanti): terbilang dan nomor kuitansi tersendiri bila Keuangan memintanya (OQ-14).
 */
class ReceiptPdf
{
    public function download(Invoice $invoice)
    {
        if ($invoice->status !== 'paid') {
            throw ValidationException::withMessages(['status' => ['Kuitansi hanya tersedia untuk invoice yang sudah lunas.']]);
        }

        $invoice->load('registration.program', 'registration.branch');

        return Pdf::loadView('exports.receipt', [
            'invoice' => $invoice,
            'registration' => $invoice->registration,
            'branch' => $invoice->registration->branch,
            'payment' => $invoice->payments()->where('status', 'confirmed')->latest('id')->first(),
        ])->setPaper('a5', 'landscape')
            ->download('kuitansi-'.str_replace('/', '-', $invoice->number).'.pdf');
    }
}
