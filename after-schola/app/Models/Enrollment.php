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

    /** Enrollment yang mencakup tanggal tertentu (BR-14): sudah mulai, dan belum berakhir atau masih aktif tanpa tanggal akhir. */
    public function scopeActiveOn(Builder $query, \DateTimeInterface|string $date): Builder
    {
        $date = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        return $query->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereDate('end_date', '>=', $date)
                ->orWhere(fn (Builder $open) => $open->whereNull('end_date')->where('status', 'aktif')));
    }

    /** Enrollment di kelas yang boleh dilihat user (cabang untuk staf, penugasan untuk trainer). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('classGroup', fn (Builder $group) => $group->visibleTo($user));
    }
}
