<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Slot untuk publik: tanpa trainer, kapasitas, atau status internal. Butuh withCount('leads'). */
class PublicTrialSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program' => ['id' => $this->program->id, 'name' => $this->program->name],
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            'starts_at' => $this->starts_at->toIso8601String(),
            'remaining' => max(0, $this->capacity - $this->leads_count),
        ];
    }
}
