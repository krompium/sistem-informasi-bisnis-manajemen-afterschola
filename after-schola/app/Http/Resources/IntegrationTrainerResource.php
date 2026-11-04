<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Kontrak API integrasi /v1 (PRD 10.6): field boleh ditambah, tidak boleh dihapus atau diubah tipenya tanpa /v2. */
class IntegrationTrainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'external_ref' => $this->external_ref,
            'is_active' => $this->is_active,
        ];
    }
}
