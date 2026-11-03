<?php

namespace App\Http\Resources;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Form pendaftaran resmi di portal (ORT-06, ORT-07). Bisa membungkus Registration yang belum disimpan
 * (isian awal dari trial dan akun orang tua, status "belum_diisi").
 *
 * @mixin Registration
 */
class ParentRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->exists ? $this->status : 'belum_diisi',
            // Terkunci begitu diajukan untuk verifikasi; kembali terbuka bila diminta revisi.
            'editable' => ! $this->exists || in_array($this->status, ['draft', 'perlu_revisi'], true),
            'revision_note' => $this->revision_note,
            'form' => [
                'student_name' => $this->student_name,
                'birth_date' => $this->birth_date?->toDateString(),
                'origin_school' => $this->origin_school,
                'origin_class' => $this->origin_class,
                'program_id' => $this->program_id,
                'parent_name' => $this->parent_name,
                'parent_phone' => $this->parent_phone,
                'parent_email' => $this->parent_email,
                'parent_address' => $this->parent_address,
            ],
        ];
    }
}
