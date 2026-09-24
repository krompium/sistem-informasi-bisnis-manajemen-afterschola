<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentGradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'period' => $this->period,
            'score' => $this->score,
            'note' => $this->note,
            'input_by' => new UserResource($this->whenLoaded('inputBy')),
            'created_at' => $this->created_at,
        ];
    }
}