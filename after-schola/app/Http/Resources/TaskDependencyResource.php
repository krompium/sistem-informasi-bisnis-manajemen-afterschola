<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDependencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'depends_on_id' => $this->depends_on_id,
            'prerequisite' => $this->whenLoaded('prerequisite', fn () => [
                'id' => $this->prerequisite->id,
                'title' => $this->prerequisite->title,
                'status' => $this->prerequisite->status->value,
            ]),
        ];
    }
}
