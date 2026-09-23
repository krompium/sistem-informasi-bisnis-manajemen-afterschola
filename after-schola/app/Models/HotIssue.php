<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotIssue extends Model
{
    protected $fillable = ['title', 'message', 'severity', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(HotIssueDismissal::class);
    }

    /**
     * Hot issue aktif yang BELUM ditutup oleh user tertentu — dasar popup setelah login.
     */
    public function scopeActiveAndUndismissedFor($query, int $userId)
    {
        return $query->where('is_active', true)
            ->whereDoesntHave('dismissals', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
    }
}
