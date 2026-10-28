<?php

namespace App\Models;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'assignee_id', 'title', 'description', 'status', 'progress', 'priority', 'category', 'due_date', 'start_date', 'github_url'];

    protected $attributes = ['status' => 'todo', 'progress' => 0, 'priority' => 'medium', 'category' => 'feature'];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'category' => TaskCategory::class,
            'progress' => 'integer',
            'due_date' => 'date',
            'start_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Baris prasyarat task ini (task lain yang harus selesai lebih dulu). */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Isolasi data: Management melihat semua task, Developer hanya task di proyek team-nya.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('management')) {
            return $query;
        }

        return $query->whereHas('project', fn ($q) => $q->visibleTo($user));
    }
}
