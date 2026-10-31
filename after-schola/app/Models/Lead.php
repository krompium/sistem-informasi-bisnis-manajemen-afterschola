<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    // 'leads' = lead lama tanpa jadwal; 'not_continued' = Leads PRD (tidak lanjut, BR-03).
    // TODO(nanti): status ini belum diisi oleh alur keputusan Modul 3/4 — hubungkan saat keputusan 'tidak lanjut' dicatat.
    public const STATUS_LABELS = [
        'leads' => 'Belum dijadwalkan',
        'trial_scheduled' => 'Trial terjadwal',
        'not_continued' => 'Tidak lanjut',
    ];

    protected $fillable = [
        'name',
        'education_level',
        'origin_school',
        'parent_email',
        'program_id',
        'branch_id',
        'parent_id',
        'trial_slot_id',
        'status',
        'note',
        'consented_at',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Lead di cabang yang boleh diakses user (BR-19). Lead lama tanpa cabang hanya untuk scope 'all'. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAllBranches() ? $query : $query->whereIn('leads.branch_id', $user->accessibleBranchIds());
    }

    /** Filter daftar dan export: branch_id, program_id, status. */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['branch_id'] ?? null, fn (Builder $q, $id) => $q->where('leads.branch_id', $id))
            ->when($filters['program_id'] ?? null, fn (Builder $q, $id) => $q->where('leads.program_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('leads.status', $status));
    }

    public function trialSlot(): BelongsTo
    {
        return $this->belongsTo(TrialSlot::class);
    }

    /** Evaluasi trial peserta ini (Modul 3); satu per slot, dan satu lead hanya punya satu slot. */
    public function trialEvaluation(): HasOne
    {
        return $this->hasOne(TrialEvaluation::class);
    }
}
