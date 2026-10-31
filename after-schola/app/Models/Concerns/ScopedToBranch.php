<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Isolasi per cabang (BR-19) untuk model dengan kolom branch_id: scope 'all' semua, 'assigned' hanya cabangnya.
 * Baris lama tanpa cabang (branch_id null) tidak pernah cocok dengan whereIn, jadi hanya terlihat scope 'all'.
 */
trait ScopedToBranch
{
    public function scopeInBranchesOf(Builder $query, User $user): Builder
    {
        return $user->hasAllBranches()
            ? $query
            : $query->whereIn($this->qualifyColumn('branch_id'), $user->accessibleBranchIds());
    }

    /** Padanan scopeInBranchesOf untuk satu record (Policy). */
    public function isInBranchesOf(User $user): bool
    {
        return $this->branch_id === null ? $user->hasAllBranches() : $user->handlesBranch($this->branch_id);
    }
}
