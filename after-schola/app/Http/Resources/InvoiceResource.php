<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eager load: registration.program (dan payments untuk daftar/detail).
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registration = $this->registration;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->daysOverdue(),
            'registration_id' => $this->registration_id,
            'student_name' => $registration?->student_name,
            'parent_name' => $registration?->parent_name,
            'parent_phone' => $registration?->parent_phone,
            'program_name' => $registration?->program?->nama,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
