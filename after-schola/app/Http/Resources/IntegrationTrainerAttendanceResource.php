<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kontrak PRD 10.4 (v1 stabil, lihat 10.6). Butuh relasi: classSession.classGroup (branch, program, assignments) dan trainer.
 * assignment_role: peran trainer di kelas itu (utama atau asisten); bila tidak ditugaskan di kelas, ia pengganti.
 */
class IntegrationTrainerAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->classSession;
        $group = $session->classGroup;

        return [
            'id' => $this->id,
            'session_date' => $session->date->toDateString(),
            'branch_code' => $group->branch?->code,
            'program' => $group->program?->name,
            'class_group' => $group->name,
            'trainer_id' => $this->trainer_id,
            'trainer_name' => $this->trainer->name,
            'trainer_external_ref' => $this->trainer->external_ref,
            'assignment_role' => $group->assignments->firstWhere('trainer_id', $this->trainer_id)?->role ?? 'pengganti',
            'mode' => $session->mode,
            'status' => $this->status,
            'check_in_at' => $this->check_in_at?->toIso8601String(),
        ];
    }
}
