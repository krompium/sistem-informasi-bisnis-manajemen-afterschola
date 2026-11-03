<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'level_override_reason' => $this->level_override_reason,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'name' => $this->student->name]),
            'class_group' => $this->whenLoaded('classGroup', fn () => ['id' => $this->classGroup->id, 'name' => $this->classGroup->name]),
        ];
    }
}
