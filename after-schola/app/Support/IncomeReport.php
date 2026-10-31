<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan pemasukan (KEU-07): payments berstatus confirmed dengan confirmed_at dalam periode,
 * dibatasi cabang user (BR-19). Dipakai JSON, Excel, dan PDF agar angkanya selalu sama.
 * Batas hari mengikuti zona tampilan (Asia/Jakarta); kolom disimpan UTC.
 */
class IncomeReport
{
    public readonly Carbon $from;

    public readonly Carbon $to;

    private ?Collection $payments = null;

    /** @param array{from: string, to: string, branch_id?: ?int, program_id?: ?int} $filters */
    public function __construct(private User $user, private array $filters)
    {
        $zone = config('app.display_timezone');
        $this->from = Carbon::parse($filters['from'], $zone)->startOfDay();
        $this->to = Carbon::parse($filters['to'], $zone)->endOfDay();
    }

    public function query(): Builder
    {
        return Payment::query()
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$this->from->copy()->utc(), $this->to->copy()->utc()])
            ->whereHas('invoice', fn (Builder $invoice) => $invoice->inBranchesOf($this->user))
            ->when($this->filters['branch_id'] ?? null, fn (Builder $q, $id) => $q
                ->whereHas('invoice.registration', fn (Builder $r) => $r->where('branch_id', $id)))
            ->when($this->filters['program_id'] ?? null, fn (Builder $q, $id) => $q
                ->whereHas('invoice.registration', fn (Builder $r) => $r->where('program_id', $id)))
            ->with('invoice.registration.program', 'invoice.registration.branch');
    }

    /** @return Collection<int, Payment> */
    public function payments(): Collection
    {
        return $this->payments ??= $this->query()->orderBy('confirmed_at')->orderBy('id')->get();
    }

    /** @return array{count: int, total: float, by_program: array, by_branch: array} */
    public function summary(): array
    {
        $payments = $this->payments();

        return [
            'count' => $payments->count(),
            'total' => (float) $payments->sum('amount'),
            'by_program' => $this->grouped($payments, 'program', fn (Payment $p) => $p->invoice->registration->program?->name ?? 'Tanpa program'),
            'by_branch' => $this->grouped($payments, 'branch', fn (Payment $p) => $p->invoice->registration->branch?->name ?? 'Tanpa cabang'),
        ];
    }

    public function filenameBase(): string
    {
        return 'pemasukan-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }

    private function grouped(Collection $payments, string $key, callable $label): array
    {
        return $payments->groupBy($label)->sortKeys()
            ->map(fn (Collection $group, string $name) => [
                $key => $name,
                'count' => $group->count(),
                'total' => (float) $group->sum('amount'),
            ])->values()->all();
    }
}
