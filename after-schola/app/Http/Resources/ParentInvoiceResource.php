<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice di Portal Orang Tua (ORT-08, ORT-09). Eager load: registration.program, payments.
 *
 * @mixin Invoice
 */
class ParentInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new InvoiceResource($this->resource))->toArray($request),
            // Boleh unggah bukti selama belum ada pembayaran yang menunggu atau sudah dikonfirmasi (rejected boleh diulang).
            'can_pay' => $this->status === 'unpaid' && $this->payments->whereIn('status', ['pending', 'confirmed'])->isEmpty(),
            'receipt_available' => $this->status === 'paid',
        ];
    }
}
