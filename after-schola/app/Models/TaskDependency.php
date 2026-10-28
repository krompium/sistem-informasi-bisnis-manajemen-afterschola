<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'depends_on_id'];

    /** Task yang bergantung (baru boleh mulai setelah prasyaratnya selesai). */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Task prasyarat. */
    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_id');
    }
}
