<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProgramTrialScheduleResource;

class ProgramResource extends JsonResource
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
                'nama' => $this->nama,
                'tipe' => $this->tipe,
                'deskripsi' => $this->deskripsi,
                'biaya' => $this->biaya,
                'status_aktif' => $this->status_aktif,
                'jadwal_trial' => ProgramTrialScheduleResource::collection($this->whenLoaded('jadwalTrial')),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ];
        }
}
