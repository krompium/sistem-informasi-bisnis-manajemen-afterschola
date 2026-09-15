<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HotIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'priority',
        'is_published',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('dismissed_at')
            ->withTimestamps();
    }

    /** Hanya issue yang sudah dipublish dan belum kedaluwarsa. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    /** Issue yang belum di-dismiss oleh user tertentu. */
    public function scopeNotDismissedBy(Builder $query, int $userId): Builder
    {
        return $query->whereDoesntHave('dismissedBy', function (Builder $q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }
}