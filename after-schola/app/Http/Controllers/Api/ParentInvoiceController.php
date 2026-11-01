<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Portal Orang Tua (ORT-09/11): bayar invoice anaknya dan unduh kuitansi. Role parent dicek di route;
 * invoice anak orang lain = 404 (AC-07). Aturan upload dan status tetap satu sumber: PaymentController dan InvoiceController.
 */
class ParentInvoiceController extends Controller
{
    // Orang tua hanya mentransfer (A02); pembayaran tunai dicatat staf Keuangan.
    public function pay(Request $request, string $id, PaymentController $payments)
    {
        $invoice = $this->ownInvoice($request, $id);
        $request->merge(['method' => 'transfer']);

        return $payments->store($request, $invoice);
    }

    public function receipt(Request $request, string $id, InvoiceController $invoices)
    {
        return $invoices->receipt($this->ownInvoice($request, $id));
    }

    private function ownInvoice(Request $request, string $id): Invoice
    {
        return Invoice::whereHas('registration.lead', fn ($q) => $q->where('parent_id', $request->user()->id))->findOrFail($id);
    }
}
