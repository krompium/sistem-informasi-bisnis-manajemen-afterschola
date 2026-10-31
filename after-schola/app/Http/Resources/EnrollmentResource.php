<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris "pipeline" = satu evaluasi trial + keputusan + pendaftaran (jika ada).
 */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $decision = $this->decision;
        $reg = $decision?->registration;

        return [
            'evaluation_id' => $this->id,
            'lead_id' => $this->lead_id,
            'program_id' => $this->program_id,
            'participant_name' => $this->participant_name,
            'origin_school' => $this->origin_school,
            'origin_class' => $this->origin_class,
            'trial_date' => $this->trial_date?->toDateString(),
            'recommendation' => $this->recommendation,
            'recommended_level' => $this->level?->name,
            'trainer_name' => $this->trainer?->name,
            // Auto-fill form pendaftaran resmi (BR-01, AC-02): data anak dari lead, orang tua dari akun yang terdaftar saat form trial.
            'prefill' => [
                'student_name' => $this->lead?->name ?? $this->participant_name,
                'origin_school' => $this->lead?->origin_school ?? $this->origin_school,
                'origin_class' => $this->origin_class,
                'program_id' => $this->program_id,
                'parent_name' => $this->lead?->parent?->name,
                'parent_phone' => $this->lead?->parent?->phone,
                'parent_email' => $this->lead?->parent?->email ?? $this->lead?->parent_email,
            ],
            'decision' => $decision ? [
                'id' => $decision->id,
                'decision' => $decision->decision,
                'reason' => $decision->reason,
                'decided_at' => $decision->decided_at?->toDateString(),
                'follow_up_date' => $decision->follow_up_date?->toDateString(),
            ] : null,
            'registration' => $reg ? [
                'id' => $reg->id,
                'status' => $reg->status,
                'program_id' => $reg->program_id,
                'revision_note' => $reg->revision_note,
                'student_name' => $reg->student_name,
                'birth_date' => $reg->birth_date?->toDateString(),
                'origin_school' => $reg->origin_school,
                'origin_class' => $reg->origin_class,
                'level' => $reg->level,
                'parent_name' => $reg->parent_name,
                'parent_phone' => $reg->parent_phone,
                'parent_email' => $reg->parent_email,
                'parent_address' => $reg->parent_address,
            ] : null,
        ];
    }
}
