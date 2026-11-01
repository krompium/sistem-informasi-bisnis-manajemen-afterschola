<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Token integrasi tanpa nilai token (hanya tampil sekali saat dibuat). Butuh tokenable ter-load. */
class IntegrationTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => str_starts_with($this->name, 'integration:') ? substr($this->name, strlen('integration:')) : $this->name,
            'abilities' => $this->abilities,
            'user' => ['id' => $this->tokenable->id, 'name' => $this->tokenable->name, 'email' => $this->tokenable->email],
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
