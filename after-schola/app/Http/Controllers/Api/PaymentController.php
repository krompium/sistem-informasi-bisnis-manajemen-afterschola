<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Modul 5 (bagian pembayaran). Mencatat pembayaran = permission "manage invoices";
 * mengonfirmasi / menolak = permission "confirm payments" (dipasang di routes).
 * Nominal selalu = nominal invoice (lunas sekaligus).
 * TODO(nanti): cicilan — tabel sudah mendukung banyak pembayaran per invoice (PRD KEU-11, [v3]).
 */
class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        $this->authorize('manage', $invoice);

        $data = $request->validate([
            'method' => ['required', 'in:transfer,tunai'],
            'paid_date' => ['required', 'date', 'before_or_equal:today'],
            'proof' => ['nullable', 'required_if:method,transfer', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        if ($invoice->status !== 'unpaid') {
            throw ValidationException::withMessages(['invoice' => ['Invoice ini tidak dapat menerima pembayaran.']]);
        }
        if ($invoice->payments()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            throw ValidationException::withMessages(['invoice' => ['Invoice ini sudah punya pembayaran yang menunggu atau sudah dikonfirmasi.']]);
        }

        $path = null;
        if ($request->hasFile('proof')) {
            $file = $request->file('proof');
            // Nama hasil generate, bukan nama asli dari client.
            $path = $file->storeAs('payment-proofs', Str::uuid().'.'.$file->extension(), 'public');
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'method' => $data['method'],
            'paid_date' => $data['paid_date'],
            'proof_path' => $path,
            'status' => 'pending',
            'recorded_by' => $request->user()->id,
        ]);

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function confirm(Request $request, Payment $payment)
    {
        $this->authorize('manage', $payment);
        $this->ensurePending($payment);

        DB::transaction(function () use ($payment, $request) {
            $payment->update([
                'status' => 'confirmed',
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
            ]);

            $invoice = $payment->invoice;
            if ($invoice->payments()->where('status', 'confirmed')->sum('amount') >= $invoice->amount) {
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            }
        });

        return new PaymentResource($payment->fresh());
    }

    public function reject(Request $request, Payment $payment)
    {
        $this->authorize('manage', $payment);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->ensurePending($payment);

        $payment->update(['status' => 'rejected', 'reject_reason' => $data['reason']]);

        return new PaymentResource($payment->fresh());
    }

    private function ensurePending(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Pembayaran ini sudah diproses.']]);
        }
    }
}
