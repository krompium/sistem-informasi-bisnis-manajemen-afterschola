<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'name' => $this->student->name]),
            'class_session' => $this->whenLoaded('classSession', fn () => [
                'id' => $this->classSession->id,
                'class_group_id' => $this->classSession->class_group_id,
                'date' => $this->classSession->date->toDateString(),
                'meeting_no' => $this->classSession->meeting_no,
            ]),
            'trainer' => $this->whenLoaded('trainer', fn () => ['id' => $this->trainer->id, 'name' => $this->trainer->name]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
