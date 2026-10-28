<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id', 'amount', 'method', 'paid_date', 'proof_path', 'status',
        'reject_reason', 'recorded_by', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function proofUrl(): ?string
    {
        return $this->proof_path ? Storage::disk('public')->url($this->proof_path) : null;
    }
}
