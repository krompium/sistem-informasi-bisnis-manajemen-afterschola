<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'paid_date' => $this->paid_date?->toDateString(),
            'status' => $this->status,
            'proof_url' => $this->proofUrl(),
            'reject_reason' => $this->reject_reason,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
