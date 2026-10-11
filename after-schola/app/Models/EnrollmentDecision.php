<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EnrollmentDecision extends Model
{
    protected $fillable = ['evaluation_id', 'decision', 'reason', 'decided_at', 'follow_up_date', 'decided_by'];

    protected function casts(): array
    {
        return [
            'decided_at' => 'date',
            'follow_up_date' => 'date',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(TrialEvaluation::class, 'evaluation_id');
    }

    public function registration(): HasOne
    {
        return $this->hasOne(Registration::class, 'decision_id');
    }
}
