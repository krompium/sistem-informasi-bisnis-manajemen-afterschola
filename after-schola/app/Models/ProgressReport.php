<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Raport satu evaluasi. Status: draft -> ditinjau -> terkirim (PRD BR-17). */
class ProgressReport extends Model
{
    protected $fillable = ['evaluation_id', 'status', 'review_note', 'reviewed_by', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Raport yang boleh dilihat user = raport dari evaluasi yang boleh dilihatnya (Evaluation::visibleTo). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('evaluation', fn (Builder $evaluation) => $evaluation->visibleTo($user));
    }
}
