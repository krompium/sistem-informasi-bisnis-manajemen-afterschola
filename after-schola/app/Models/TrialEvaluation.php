<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrialEvaluation extends Model
{
    use ScopedToBranch;

    protected $fillable = [
        'lead_id', 'branch_id', 'trial_slot_id', 'program_id', 'participant_name', 'origin_school', 'origin_class',
        'trainer_id', 'trial_date', 'attendance',
        'score_enthusiasm', 'score_understanding', 'score_focus', 'score_collaboration',
        'strengths', 'improvements', 'recommended_level_id', 'recommendation',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TrialSlot::class, 'trial_slot_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(ProgramLevel::class, 'recommended_level_id');
    }

    public function decision(): HasOne
    {
        return $this->hasOne(EnrollmentDecision::class, 'evaluation_id');
    }

    /** Staf evaluasi: per cabang. Trainer: hanya evaluasi miliknya (trainer slot). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->can('manage trial evaluations')
            ? $query->inBranchesOf($user)
            : $query->where('trial_evaluations.trainer_id', $user->id);
    }
}
