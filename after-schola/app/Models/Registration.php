<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    protected $fillable = [
        'decision_id', 'lead_id', 'program_id',
        'student_name', 'birth_date', 'origin_school', 'origin_class', 'level',
        'parent_name', 'parent_phone', 'parent_email', 'parent_address',
        'status', 'student_id', 'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(EnrollmentDecision::class, 'decision_id');
    }
}
