<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpoReport extends Model
{
    protected $fillable = [
        'school_id', 'trainer_id', 'date', 'team_name', 'rating',
        'on_schedule', 'enthusiasm', 'has_issue', 'issue_note', 'doc_url',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rating' => 'integer',
            'has_issue' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
