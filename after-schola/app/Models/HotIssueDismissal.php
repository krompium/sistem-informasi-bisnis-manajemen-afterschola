<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotIssueDismissal extends Model
{
    public $timestamps = false;

    protected $fillable = ['hot_issue_id', 'user_id', 'dismissed_at'];

    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }

    public function hotIssue(): BelongsTo
    {
        return $this->belongsTo(HotIssue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
