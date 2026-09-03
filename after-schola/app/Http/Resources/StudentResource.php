<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'classroom_id' => $this->classroom_id,
            'name' => $this->name,
            'origin_class' => $this->origin_class,
            'created_by' => $this->created_by,
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'school' => new SchoolResource($this->whenLoaded('school')),
        ];
    }
}
