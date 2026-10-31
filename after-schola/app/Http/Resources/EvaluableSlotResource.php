<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slot trial + peserta + status evaluasi (TRN-04). Eager load: branch, program.levels, leads.trialEvaluation.
 */
class EvaluableSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            'program' => [
                'id' => $this->program->id,
                'name' => $this->program->name,
                'levels' => $this->program->levels->map(fn ($level) => ['id' => $level->id, 'name' => $level->name])->values(),
            ],
            'participants' => $this->leads->map(fn ($lead) => [
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'origin_school' => $lead->origin_school,
                'evaluation_id' => $lead->trialEvaluation?->id,
                'attendance' => $lead->trialEvaluation?->attendance,
            ])->values(),
        ];
    }
}
