<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'education_level' => $this->education_level,
            'origin_school' => $this->origin_school,
            'parent_email' => $this->parent_email,
            'program_id' => $this->program_id,
            'program_trial_schedule_id' => $this->program_trial_schedule_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
