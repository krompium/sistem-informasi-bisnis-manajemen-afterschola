<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $fillable = [
        'school_id', 'classroom_id', 'trainer_id',
        'mode', 'date', 'start_time', 'end_time', 'meeting_no', 'is_locked', 'started_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_locked' => 'boolean',
            'meeting_no' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function trainerAttendances(): HasMany
    {
        return $this->hasMany(TrainerAttendance::class);
    }
}
