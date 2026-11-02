<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParentInvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Support\PaymentRecorder;
use App\Support\ReceiptPdf;
use Illuminate\Http\Request;

/**
 * Portal Orang Tua (ORT-08, ORT-09): invoice anak, unggah bukti transfer, kuitansi. MVP: transfer manual + bukti (A-02),
 * dikonfirmasi Keuangan. Role "parent" dijaga middleware route; invoice orang lain = 404 (BR-19).
 */
class ParentInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::ownedBy($request->user())->with('registration.program', 'payments')->latest('id')->get();
        $setting = PaymentSetting::current();

        return ParentInvoiceResource::collection($invoices)->additional(['meta' => [
            'payment_info' => ['instructions' => $setting->payment_instructions, 'bank_account' => $setting->bank_account],
        ]]);
    }

    public function pay(Request $request, int $invoice, PaymentRecorder $recorder)
    {
        $invoice = $this->own($request, $invoice);
        $data = $request->validate([
            'proof' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
            'paid_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $payment = $recorder->record($invoice, 'transfer', $data['paid_date'] ?? now()->toDateString(), $request->file('proof'), $request->user());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function receipt(Request $request, int $invoice, ReceiptPdf $receipt)
    {
        return $receipt->download($this->own($request, $invoice));
    }

    private function own(Request $request, int $id): Invoice
    {
        return Invoice::ownedBy($request->user())->findOrFail($id);
    }
}
