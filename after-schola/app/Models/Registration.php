<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    use ScopedToBranch;

    protected $fillable = [
        'decision_id', 'lead_id', 'branch_id', 'program_id',
        'student_name', 'birth_date', 'origin_school', 'origin_class', 'level',
        'parent_name', 'parent_phone', 'parent_email', 'parent_address',
        'status', 'student_id', 'registered_by',
        'revision_note', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Invoice terbaru yang belum di-void. */
    public function activeInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('status', '!=', 'void'));
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(EnrollmentDecision::class, 'decision_id');
    }
}
