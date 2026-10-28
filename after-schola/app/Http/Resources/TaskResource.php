<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'assignee_id' => $this->assignee_id,
            // null bila user assignee sudah dihapus (nullOnDelete di migration).
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee
                ? ['id' => $this->assignee->id, 'name' => $this->assignee->name]
                : null),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'progress' => $this->progress,
            'priority' => $this->priority->value,
            'category' => $this->category->value,
            'due_date' => $this->due_date->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'github_url' => $this->github_url,
            // Hanya ada bila relasinya dihitung (index/show/store/update di TaskController).
            'checklist_total' => $this->whenNotNull($this->checklist_items_count),
            'checklist_done' => $this->whenNotNull($this->checklist_done_count),
            'attachments_count' => $this->whenNotNull($this->attachments_count),
            // Jumlah prasyarat yang belum Done (task "terblokir" bila > 0).
            'blocked_count' => $this->whenNotNull($this->blocked_count),
            'created_at' => $this->created_at,
        ];
    }
}
