<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    protected $fillable = ['name', 'code', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Cabang yang boleh dilihat user: scope 'all' semua, 'assigned' hanya miliknya.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAllBranches() ? $query : $query->whereIn('branches.id', $user->accessibleBranchIds());
    }
}
