<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'education_level' => $this->education_level,
            'origin_school' => $this->origin_school,
            'parent_email' => $this->parent_email,
            'program_id' => $this->program_id,
            'trial_slot_id' => $this->trial_slot_id,
            'trial_slot' => $this->whenLoaded('trialSlot', fn () => $this->trialSlot ? [
                'starts_at' => $this->trialSlot->starts_at->toIso8601String(),
                'program' => $this->trialSlot->program->name,
                'branch' => $this->trialSlot->branch->name,
            ] : null),
            'status' => $this->status,
            // Khusus staf: respons publik pendaftaran (tanpa login) tidak memuat ini.
            'note' => $this->when($request->user() !== null, $this->note),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
                'email' => $this->parent->email,
                'phone' => $this->parent->phone,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'program' => $this->whenLoaded('program', fn () => $this->program ? ['id' => $this->program->id, 'name' => $this->program->name] : null),
            'created_at' => $this->created_at,
        ];
    }
}
