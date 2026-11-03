<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Keanggotaan siswa di sebuah kelas reguler (PRD "enrollment"). Beda dari EnrollmentDecision (keputusan trial). */
class Enrollment extends Model
{
    protected $fillable = ['class_group_id', 'student_id', 'start_date', 'end_date', 'status', 'level_override_reason'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Enrollment di kelas yang boleh dilihat user (cabang untuk staf, penugasan untuk trainer). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('classGroup', fn (Builder $group) => $group->visibleTo($user));
    }
}
