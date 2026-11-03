<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'program' => $this->whenLoaded('program', fn () => ['id' => $this->program->id, 'name' => $this->program->name]),
            'level' => $this->whenLoaded('level', fn () => ['id' => $this->level->id, 'name' => $this->level->name]),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'assignments' => TrainerAssignmentResource::collection($this->whenLoaded('assignments')),
        ];
    }
}
