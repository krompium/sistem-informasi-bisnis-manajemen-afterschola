<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_group_id' => $this->class_group_id,
            'role' => $this->role,
            'trainer' => $this->whenLoaded('trainer', fn () => ['id' => $this->trainer->id, 'name' => $this->trainer->name]),
        ];
    }
}
