<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrialSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'program' => $this->whenLoaded('program', fn () => ['id' => $this->program->id, 'name' => $this->program->name]),
            'trainer' => $this->whenLoaded('trainer', fn () => ['id' => $this->trainer->id, 'name' => $this->trainer->name]),
            'starts_at' => $this->starts_at->toIso8601String(),
            'capacity' => $this->capacity,
            'is_open' => $this->is_open,
            'booked_count' => $this->whenCounted('leads'),
            'remaining' => $this->whenCounted('leads', fn (int $booked) => max(0, $this->capacity - $booked)),
        ];
    }
}
