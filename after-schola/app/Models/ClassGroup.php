<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kelas reguler: kelompok siswa pada program, level, dan cabang tertentu (PRD "class group"). */
class ClassGroup extends Model
{
    /** Pemegang salah satu permission ini adalah staf kelas: dibatasi cabang, bukan penugasan. */
    public const STAFF_PERMISSIONS = ['manage classes', 'assign trainers', 'manage schedule'];

    protected $fillable = ['program_id', 'level_id', 'branch_id', 'name', 'capacity', 'is_active'];

    // Sama dengan default kolom, agar instance baru (respons 201) tidak membawa is_active = null.
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(ProgramLevel::class, 'level_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Isi kelas saat ini: hanya yang berstatus aktif yang memakai kursi (BR-11). */
    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', 'aktif');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainerAssignment::class);
    }

    /**
     * Kelas yang boleh dilihat user: staf kelas per cabang (BR-19), trainer hanya kelas
     * yang ditugaskan kepadanya sebagai utama atau asisten (PRD §9).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAny(self::STAFF_PERMISSIONS)) {
            return $user->hasAllBranches() ? $query : $query->whereIn('class_groups.branch_id', $user->accessibleBranchIds());
        }

        return $query->whereHas('assignments', fn (Builder $a) => $a->where('trainer_id', $user->id));
    }
}
