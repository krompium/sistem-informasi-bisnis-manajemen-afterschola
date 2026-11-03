<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Kontrak /api/integration/v1/payments (aturan versi sama dengan invoice). Tanpa berkas bukti bayar. Eager load: invoice.registration.branch. */
class IntegrationPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice->number,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'paid_date' => $this->paid_date?->toDateString(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'reject_reason' => $this->reject_reason,
            'branch_code' => $this->invoice->registration->branch?->code,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
