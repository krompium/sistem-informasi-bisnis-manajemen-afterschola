<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrialSlot extends Model
{
    protected $fillable = ['branch_id', 'program_id', 'trainer_id', 'starts_at', 'capacity', 'is_open'];

    // Sama dengan default kolom, agar instance baru (respons 201) tidak membawa is_open = null.
    protected $attributes = ['is_open' => true];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'capacity' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /** Peserta trial: lead yang memesan slot ini (menentukan sisa kursi). */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** Slot yang masih bisa dipesan publik: dibuka, belum mulai, masih ada kursi, program dan cabang aktif. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('trial_slots.is_open', true)
            ->where('trial_slots.starts_at', '>', now())
            ->whereRaw('trial_slots.capacity > (select count(*) from leads where leads.trial_slot_id = trial_slots.id)')
            ->whereHas('program', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('branch', fn (Builder $q) => $q->where('is_active', true));
    }

    /** Slot di cabang yang boleh diakses user: scope 'all' semua, 'assigned' hanya cabangnya (BR-19). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAllBranches() ? $query : $query->whereIn('trial_slots.branch_id', $user->accessibleBranchIds());
    }
}
