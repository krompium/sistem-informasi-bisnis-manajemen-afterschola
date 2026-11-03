<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat pembayaran baru berstatus pending (Modul 5), oleh staf atau orang tua lewat portal.
 * Nominal selalu = nominal invoice (lunas sekaligus). Konfirmasi atau penolakan oleh Keuangan (PaymentController).
 * TODO(nanti): cicilan — tabel sudah mendukung banyak pembayaran per invoice (PRD KEU-11, [v3]).
 */
class PaymentRecorder
{
    public function record(Invoice $invoice, string $method, string $paidDate, ?UploadedFile $proof, User $by): Payment
    {
        if ($invoice->status !== 'unpaid') {
            throw ValidationException::withMessages(['invoice' => ['Invoice ini tidak dapat menerima pembayaran.']]);
        }
        if ($invoice->payments()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            throw ValidationException::withMessages(['invoice' => ['Invoice ini sudah punya pembayaran yang menunggu atau sudah dikonfirmasi.']]);
        }

        // Nama hasil generate, bukan nama asli dari client.
        $path = $proof?->storeAs('payment-proofs', Str::uuid().'.'.$proof->extension(), 'public');

        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'method' => $method,
            'paid_date' => $paidDate,
            'proof_path' => $path,
            'status' => 'pending',
            'recorded_by' => $by->id,
        ]);
    }
}
