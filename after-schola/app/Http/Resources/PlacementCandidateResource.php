<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Registrasi yang siap ditempatkan ke kelas (MGT-31). `id` = id registrasi. */
class PlacementCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $level = $this->decision?->evaluation?->level;

        return [
            'id' => $this->id,
            'student_name' => $this->student_name,
            'parent_name' => $this->parent_name,
            'program' => $this->whenLoaded('program', fn () => ['id' => $this->program->id, 'name' => $this->program->name]),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'recommended_level' => $level ? ['id' => $level->id, 'name' => $level->name] : null,
        ];
    }
}
