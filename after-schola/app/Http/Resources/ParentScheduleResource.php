<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jadwal satu anak aktif di satu kelas (ORT-10). Eager load: student, classGroup.(program|level|branch|assignments.trainer)
 * dan relasi upcomingSessions yang diisi controller.
 *
 * @mixin Enrollment
 */
class ParentScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $group = $this->classGroup;

        return [
            'enrollment_id' => $this->id,
            'student_name' => $this->student->name,
            'class' => [
                'id' => $group->id,
                'name' => $group->name,
                'program' => $group->program?->name,
                'level' => $group->level?->name,
                'branch' => $group->branch?->name,
            ],
            'trainers' => $group->assignments->map(fn ($a) => ['name' => $a->trainer?->name, 'role' => $a->role])->values(),
            'sessions' => $this->upcomingSessions->map(fn ($session) => [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start_time' => $session->start_time ? substr($session->start_time, 0, 5) : null,
                'end_time' => $session->end_time ? substr($session->end_time, 0, 5) : null,
                'meeting_no' => $session->meeting_no,
                'mode' => $session->mode,
                'trainer' => $session->trainer?->name,
            ])->values(),
        ];
    }
}
