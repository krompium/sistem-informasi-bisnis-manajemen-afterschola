<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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

    /**
     * Pemasukan (KPI income): payment confirmed dengan confirmed_at dalam periode [from, to] (tanggal lokal),
     * dibatasi cabang user (BR-19) dan opsional satu cabang. $filters: from, to, branch_id.
     */
    public function scopeIncome(Builder $query, User $user, array $filters): Builder
    {
        $tz = config('app.display_timezone');
        $start = Carbon::parse($filters['from'], $tz)->startOfDay()->timezone(config('app.timezone'));
        $end = Carbon::parse($filters['to'], $tz)->endOfDay()->timezone(config('app.timezone'));

        return $query->where('payments.status', 'confirmed')
            ->whereBetween('payments.confirmed_at', [$start, $end])
            ->whereHas('invoice.registration', function (Builder $r) use ($user, $filters) {
                $r->inBranchesOf($user);
                if (! empty($filters['branch_id'])) {
                    $r->where('registrations.branch_id', $filters['branch_id']);
                }
            });
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
