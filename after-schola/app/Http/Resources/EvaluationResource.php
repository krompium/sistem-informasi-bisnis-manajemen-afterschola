<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $program = $this->enrollment?->classGroup?->program;

        return [
            'id' => $this->id,
            'period' => $this->period,
            'summary' => $this->summary,
            'enrollment' => $this->whenLoaded('enrollment', fn () => [
                'id' => $this->enrollment->id,
                'status' => $this->enrollment->status,
                'student' => ['id' => $this->enrollment->student->id, 'name' => $this->enrollment->student->name],
                'class_group' => ['id' => $this->enrollment->classGroup->id, 'name' => $this->enrollment->classGroup->name],
            ]),
            'evaluator' => $this->whenLoaded('evaluator', fn () => ['id' => $this->evaluator->id, 'name' => $this->evaluator->name]),
            'report' => $this->whenLoaded('report', fn () => [
                'id' => $this->report->id,
                'status' => $this->report->status,
                'review_note' => $this->report->review_note,
                'sent_at' => $this->report->sent_at,
            ]),
            // Seluruh kriteria rubrik program beserta skor (null = belum dinilai); hanya di endpoint detail.
            'criteria' => $this->when($program?->relationLoaded('rubricCriteria') && $this->relationLoaded('criteria'), function () use ($program) {
                $scores = $this->criteria->pluck('pivot.score', 'id');

                return $program->rubricCriteria->map(fn ($criterion) => [
                    'id' => $criterion->id,
                    'name' => $criterion->name,
                    'max_score' => $criterion->max_score,
                    'score' => $scores->get($criterion->id),
                ])->values();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
