<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'title', 'is_done'];

    protected $attributes = ['is_done' => false];

    protected function casts(): array
    {
        return ['is_done' => 'boolean'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
