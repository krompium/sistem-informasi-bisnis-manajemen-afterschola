<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerAttendance extends Model
{
    protected $fillable = [
        'class_session_id', 'trainer_id', 'check_in_at',
        'status', 'photo_path', 'latitude', 'longitude', 'note',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
