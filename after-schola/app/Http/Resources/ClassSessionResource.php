<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'classroom_id' => $this->classroom_id,
            'trainer_id' => $this->trainer_id,
            'mode' => $this->mode,
            'date' => $this->date?->toDateString(),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'meeting_no' => $this->meeting_no,
            'is_locked' => $this->is_locked,
            'started_at' => $this->started_at,
            'note' => $this->note,
            'present_count' => $this->whenNotNull($this->present_count),
            'school' => new SchoolResource($this->whenLoaded('school')),
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'student_attendances' => StudentAttendanceResource::collection($this->whenLoaded('studentAttendances')),
            'trainer_attendances' => TrainerAttendanceResource::collection($this->whenLoaded('trainerAttendances')),
        ];
    }
}
