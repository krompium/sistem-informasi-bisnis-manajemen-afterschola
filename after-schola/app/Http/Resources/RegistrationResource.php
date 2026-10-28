<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pendaftaran resmi dalam antrian verifikasi (Modul 5). Eager load: decision, program, activeInvoice.
 */
class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoice = $this->activeInvoice;

        return [
            'id' => $this->id,
            'evaluation_id' => $this->decision?->evaluation_id,
            'status' => $this->status,
            'revision_note' => $this->revision_note,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'student_name' => $this->student_name,
            'birth_date' => $this->birth_date?->toDateString(),
            'origin_school' => $this->origin_school,
            'origin_class' => $this->origin_class,
            'level' => $this->level,
            'parent_name' => $this->parent_name,
            'parent_phone' => $this->parent_phone,
            'parent_email' => $this->parent_email,
            'parent_address' => $this->parent_address,
            'program_id' => $this->program_id,
            'program_name' => $this->program?->nama,
            'program_price' => $this->program ? (float) $this->program->biaya : null,
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'amount' => (float) $invoice->amount,
                'status' => $invoice->status,
            ] : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
