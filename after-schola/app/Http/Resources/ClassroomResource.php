<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'name' => $this->name,
            'level' => $this->level,
            'students_count' => $this->whenNotNull($this->students_count),
            'school' => new SchoolResource($this->whenLoaded('school')),
        ];
    }
}
