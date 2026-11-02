<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modul 5 (bagian pembayaran). Mencatat pembayaran = permission "manage invoices";
 * mengonfirmasi / menolak = permission "confirm payments" (dipasang di routes).
 * Aturan pencatatan (nominal, satu pembayaran aktif per invoice) ada di PaymentRecorder.
 */
class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice, PaymentRecorder $recorder)
    {
        $this->authorize('view', $invoice); // sebelum validasi: di luar cabang harus 404, bukan 422
        $data = $request->validate([
            'method' => ['required', 'in:transfer,tunai'],
            'paid_date' => ['required', 'date', 'before_or_equal:today'],
            'proof' => ['nullable', 'required_if:method,transfer', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        $payment = $recorder->record($invoice, $data['method'], $data['paid_date'], $request->file('proof'), $request->user());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function confirm(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment->invoice);
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
        $this->authorize('view', $payment->invoice);
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
