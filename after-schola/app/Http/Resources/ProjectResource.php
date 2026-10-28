<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'start_date' => $this->start_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
