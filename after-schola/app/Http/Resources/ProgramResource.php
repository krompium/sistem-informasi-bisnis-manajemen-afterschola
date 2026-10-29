<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProgramTrialScheduleResource;

class ProgramResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'jenjang' => $this->jenjang,
                'tipe' => $this->tipe,
                'description' => $this->description,
                'price' => $this->price,
                'is_active' => $this->is_active,
                'levels' => ProgramLevelResource::collection($this->whenLoaded('levels')),
                'rubric_criteria' => RubricCriterionResource::collection($this->whenLoaded('rubricCriteria')),
                'jadwal_trial' => ProgramTrialScheduleResource::collection($this->whenLoaded('jadwalTrial')),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ];
        }
}
