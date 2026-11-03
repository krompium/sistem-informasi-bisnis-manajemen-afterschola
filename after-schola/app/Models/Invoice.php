<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'registration_id', 'number', 'amount', 'due_date', 'status',
        'issued_at', 'paid_at', 'last_reminded_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Isolasi cabang (BR-19) mengikuti cabang pendaftarannya; invoice tidak menyimpan branch_id sendiri. */
    public function scopeInBranchesOf(Builder $query, User $user): Builder
    {
        return $user->hasAllBranches()
            ? $query
            : $query->whereHas('registration', fn (Builder $r) => $r->inBranchesOf($user));
    }

    public function isInBranchesOf(User $user): bool
    {
        return $this->registration->isInBranchesOf($user);
    }

    /**
     * Nomor berikutnya: INV/{kode cabang}/{YYYYMM}/{urut 4 digit}; urutan dihitung per cabang (BR-06).
     * Pendaftaran lama tanpa cabang memakai format lama INV/{YYYYMM}/{urut}.
     * Panggil di dalam transaksi. Invoice tidak pernah dihapus, jadi hitungan aman dipakai sebagai urutan.
     * Kode cabang hanya [A-Z0-9], jadi aman dipakai di LIKE.
     */
    public static function nextNumber(?string $branchCode = null): string
    {
        $prefix = 'INV/'.($branchCode ? $branchCode.'/' : '').now()->format('Ym').'/';
        $count = static::where('number', 'like', $prefix.'%')->lockForUpdate()->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Hari keterlambatan; 0 bila belum jatuh tempo atau sudah tidak berstatus unpaid. */
    public function daysOverdue(): int
    {
        if ($this->status !== 'unpaid' || ! $this->due_date->lt(today())) {
            return 0;
        }

        return (int) abs($this->due_date->startOfDay()->diffInDays(today()));
    }

    public function isOverdue(): bool
    {
        return $this->daysOverdue() > 0;
    }
}
