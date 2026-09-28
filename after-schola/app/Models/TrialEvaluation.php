<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialEvaluation extends Model
{
    protected $fillable = [
        'lead_id', 'program_id', 'participant_name', 'origin_school', 'origin_class',
        'trainer_id', 'trial_date', 'attendance',
        'score_enthusiasm', 'score_understanding', 'score_focus', 'score_collaboration',
        'strengths', 'improvements', 'recommended_level', 'recommendation',
    ];

    protected function casts(): array
    {
        return [
            'trial_date' => 'date',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
