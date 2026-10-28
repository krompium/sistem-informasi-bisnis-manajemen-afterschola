<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role_in_team' => $user->pivot->role_in_team,
            ])->values()),
            'projects_count' => $this->whenNotNull($this->projects_count),
            'created_at' => $this->created_at,
        ];
    }
}
