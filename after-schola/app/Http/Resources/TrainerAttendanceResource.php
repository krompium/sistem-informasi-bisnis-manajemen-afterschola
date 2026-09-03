<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_session_id' => $this->class_session_id,
            'trainer_id' => $this->trainer_id,
            'check_in_at' => $this->check_in_at,
            'status' => $this->status,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'note' => $this->note,
            'trainer' => new UserResource($this->whenLoaded('trainer')),
        ];
    }
}
