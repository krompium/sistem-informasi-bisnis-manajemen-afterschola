<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Sesi kelas reguler (class_sessions dengan class_group_id). is_substitute: pelaksana bukan trainer yang ditugaskan ke kelas. */
class ClassGroupSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $group = $this->relationLoaded('classGroup') ? $this->classGroup : null;

        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'meeting_no' => $this->meeting_no,
            'mode' => $this->mode,
            'is_locked' => $this->is_locked,
            'note' => $this->note,
            'class_group' => $group ? ['id' => $group->id, 'name' => $group->name] : null,
            'trainer' => $this->whenLoaded('trainer', fn () => ['id' => $this->trainer->id, 'name' => $this->trainer->name]),
            'is_substitute' => $group?->relationLoaded('assignments') ? ! $group->assignments->contains('trainer_id', $this->trainer_id) : null,
        ];
    }
}
