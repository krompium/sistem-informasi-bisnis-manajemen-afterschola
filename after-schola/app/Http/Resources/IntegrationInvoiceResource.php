<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kontrak /api/integration/v1/invoices (stabil: field boleh ditambah, tidak dihapus atau diubah tipenya tanpa /v2).
 * Hanya yang dibutuhkan rekonsiliasi: tanpa kontak orang tua. Eager load: registration.program, registration.branch.
 */
class IntegrationInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registration = $this->registration;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'student_name' => $registration->student_name,
            'parent_name' => $registration->parent_name,
            'program' => $registration->program?->name,
            'branch_code' => $registration->branch?->code,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
