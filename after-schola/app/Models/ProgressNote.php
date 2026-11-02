<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan progres siswa pada satu sesi kelas reguler, ditulis trainer kelas tersebut (PRD TRN-08, BR-16). */
class ProgressNote extends Model
{
    /** Staf yang boleh membaca catatan (per cabang): koordinator kelas dan peninjau raport (MGT-44). */
    public const STAFF_READ_PERMISSIONS = [...ClassGroup::STAFF_PERMISSIONS, 'review reports'];

    protected $fillable = ['class_session_id', 'student_id', 'trainer_id', 'note'];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /** Staf per cabang (BR-19); trainer untuk kelas yang ditugaskan atau sesi yang ia isi sebagai pengganti. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAny(self::STAFF_READ_PERMISSIONS)) {
            return $user->hasAllBranches()
                ? $query
                : $query->whereHas('classSession.classGroup', fn (Builder $group) => $group->whereIn('class_groups.branch_id', $user->accessibleBranchIds()));
        }

        return $query->whereHas('classSession', fn (Builder $session) => $session->visibleToRegular($user));
    }
}
