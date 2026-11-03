<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $fillable = [
        'school_id', 'classroom_id', 'class_group_id', 'trainer_id',
        'mode', 'date', 'start_time', 'end_time', 'meeting_no', 'is_locked', 'started_at', 'note',
    ];

    // Sama dengan default kolom, agar instance baru (respons 201) tidak membawa is_locked = null.
    protected $attributes = ['is_locked' => false];

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

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    /**
     * Sesi kelas reguler yang boleh dilihat user: staf per cabang (BR-19); trainer untuk kelas yang ditugaskan
     * atau sesi yang ia isi sebagai pengganti (PRD TRN-02). Pertemuan sekolah (tanpa class_group_id) tidak pernah ikut.
     */
    public function scopeVisibleToRegular(Builder $query, User $user): Builder
    {
        $query->whereNotNull('class_sessions.class_group_id');

        if ($user->canAny(ClassGroup::STAFF_PERMISSIONS)) {
            return $user->hasAllBranches()
                ? $query
                : $query->whereHas('classGroup', fn (Builder $group) => $group->whereIn('branch_id', $user->accessibleBranchIds()));
        }

        return $query->where(fn (Builder $q) => $q
            ->where('class_sessions.trainer_id', $user->id)
            ->orWhereHas('classGroup', fn (Builder $group) => $group->visibleTo($user)));
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
