<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_session_id' => $this->class_session_id,
            'student_id' => $this->student_id,
            'is_present' => $this->is_present,
            'note' => $this->note,
            'student' => new StudentResource($this->whenLoaded('student')),
        ];
    }
}
