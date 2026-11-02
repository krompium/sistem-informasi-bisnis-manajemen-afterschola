<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Evaluasi periodik siswa di sebuah kelas, dinilai dengan rubrik program (PRD TRN-09, BR-16). */
class Evaluation extends Model
{
    protected $fillable = ['enrollment_id', 'period', 'summary', 'evaluator_id'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(ProgressReport::class);
    }

    /** Skor per kriteria rubrik; nilai ada di pivot `score`. */
    public function criteria(): BelongsToMany
    {
        return $this->belongsToMany(RubricCriterion::class, 'evaluation_scores', 'evaluation_id', 'criterion_id')
            ->withPivot('score')->withTimestamps();
    }

    /**
     * Ortu: hanya raport terkirim milik anaknya. Peninjau (review reports): per cabang (BR-19).
     * Lainnya (trainer): evaluasi yang ia tulis sendiri, termasuk draft.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('parent')) {
            return $query
                ->whereHas('enrollment.student', fn (Builder $student) => $student->where('students.parent_id', $user->id))
                ->whereHas('report', fn (Builder $report) => $report->where('progress_reports.status', 'terkirim'));
        }

        if ($user->can('review reports')) {
            return $user->hasAllBranches()
                ? $query
                : $query->whereHas('enrollment.classGroup', fn (Builder $group) => $group->whereIn('class_groups.branch_id', $user->accessibleBranchIds()));
        }

        return $query->where('evaluations.evaluator_id', $user->id);
    }
}
