<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** Slot di cabang yang boleh diakses user: scope 'all' semua, 'assigned' hanya cabangnya (BR-19). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAllBranches() ? $query : $query->whereIn('trial_slots.branch_id', $user->accessibleBranchIds());
    }
}
