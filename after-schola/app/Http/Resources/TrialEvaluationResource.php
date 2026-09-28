<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrialEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scores = array_filter([
            $this->score_enthusiasm,
            $this->score_understanding,
            $this->score_focus,
            $this->score_collaboration,
        ], fn ($v) => $v !== null);

        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'program_id' => $this->program_id,
            'participant_name' => $this->participant_name,
            'origin_school' => $this->origin_school,
            'origin_class' => $this->origin_class,
            'trial_date' => $this->trial_date?->toDateString(),
            'attendance' => $this->attendance,
            'score_enthusiasm' => $this->score_enthusiasm,
            'score_understanding' => $this->score_understanding,
            'score_focus' => $this->score_focus,
            'score_collaboration' => $this->score_collaboration,
            'average_score' => $scores ? round(array_sum($scores) / count($scores), 1) : null,
            'strengths' => $this->strengths,
            'improvements' => $this->improvements,
            'recommended_level' => $this->recommended_level,
            'recommendation' => $this->recommendation,
            'trainer_id' => $this->trainer_id,
            'trainer_name' => $this->whenLoaded('trainer', fn () => $this->trainer?->name),
            'created_at' => $this->created_at,
        ];
    }
}
