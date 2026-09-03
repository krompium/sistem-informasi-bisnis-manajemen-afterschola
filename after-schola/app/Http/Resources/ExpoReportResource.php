<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpoReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'trainer_id' => $this->trainer_id,
            'date' => $this->date?->toDateString(),
            'team_name' => $this->team_name,
            'rating' => $this->rating,
            'on_schedule' => $this->on_schedule,
            'enthusiasm' => $this->enthusiasm,
            'has_issue' => $this->has_issue,
            'issue_note' => $this->issue_note,
            'doc_url' => $this->doc_url,
            'school' => new SchoolResource($this->whenLoaded('school')),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
        ];
    }
}
