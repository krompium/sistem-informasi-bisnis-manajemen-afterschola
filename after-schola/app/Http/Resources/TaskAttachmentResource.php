<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskAttachmentResource extends JsonResource
{
    // Path file di disk sengaja tidak ikut dikirim; unduhan lewat endpoint ber-otorisasi.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'user' => $this->whenLoaded('user', fn () => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name]
                : null),
            'created_at' => $this->created_at,
        ];
    }
}
