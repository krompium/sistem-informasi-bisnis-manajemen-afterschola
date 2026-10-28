<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'note' => $this->note,
            // null bila pembuatnya sudah dihapus atau entri otomatis tanpa user.
            'user' => $this->whenLoaded('user', fn () => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name]
                : null),
            'created_at' => $this->created_at,
        ];
    }
}
