<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramTrialScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
        {
            return [
                'id' => $this->id,
                'hari' => $this->hari,
                'jam_mulai' => $this->jam_mulai,
                'jam_selesai' => $this->jam_selesai,
            ];
        }
}
