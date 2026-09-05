<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'pic_name' => $this->pic_name,
            'pic_phone' => $this->pic_phone,
            'students_count' => $this->whenNotNull($this->students_count),
            'classrooms' => ClassroomResource::collection($this->whenLoaded('classrooms')),
            'trainers' => UserResource::collection($this->whenLoaded('trainers')),
            'created_at' => $this->created_at,
        ];
    }
}
