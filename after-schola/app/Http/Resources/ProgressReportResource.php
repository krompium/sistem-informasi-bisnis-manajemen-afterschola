<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Raport untuk antrian tinjau. Skor dan ringkasan dibuka lewat GET /evaluations/{evaluation.id}. */
class ProgressReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'review_note' => $this->review_note,
            'sent_at' => $this->sent_at,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null),
            'evaluation' => $this->whenLoaded('evaluation', fn () => [
                'id' => $this->evaluation->id,
                'period' => $this->evaluation->period,
                'student' => ['id' => $this->evaluation->enrollment->student->id, 'name' => $this->evaluation->enrollment->student->name],
                'class_group' => ['id' => $this->evaluation->enrollment->classGroup->id, 'name' => $this->evaluation->enrollment->classGroup->name],
                'evaluator' => ['id' => $this->evaluation->evaluator->id, 'name' => $this->evaluation->evaluator->name],
            ]),
            'updated_at' => $this->updated_at,
        ];
    }
}
