<?php

namespace App\Support;

use App\Http\Requests\IncomeReportRequest;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan pemasukan (KEU-07). Pemasukan = payments confirmed dengan confirmed_at dalam periode;
 * periode = hari penuh zona tampilan (Asia/Jakarta), default awal bulan sampai hari ini.
 * Dibatasi scope cabang user (BR-19). Dipakai JSON, Excel, dan PDF.
 */
class IncomeReport
{
    public readonly Carbon $from;

    public readonly Carbon $to;

    public function __construct(private User $user, ?string $from, ?string $to, private ?int $branchId = null, private ?int $programId = null)
    {
        $tz = config('app.display_timezone');
        $this->from = $from ? Carbon::parse($from, $tz)->startOfDay() : Carbon::now($tz)->startOfMonth();
        $this->to = $to ? Carbon::parse($to, $tz)->endOfDay() : Carbon::now($tz)->endOfDay();
    }

    public static function fromRequest(IncomeReportRequest $request): self
    {
        return new self($request->user(), $request->input('from'), $request->input('to'), $request->integer('branch_id') ?: null, $request->integer('program_id') ?: null);
    }

    public function filenameBase(): string
    {
        return 'pemasukan-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }

    /** Pembayaran terkonfirmasi dalam periode, sudah dibatasi cabang dan filter. */
    public function query(): Builder
    {
        return Payment::query()
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$this->from->copy()->utc(), $this->to->copy()->utc()])
            ->whereHas('invoice.registration', fn (Builder $registration) => $registration
                ->inBranchesOf($this->user)
                ->when($this->branchId, fn (Builder $q, $id) => $q->where('registrations.branch_id', $id))
                ->when($this->programId, fn (Builder $q, $id) => $q->where('registrations.program_id', $id)))
            ->with('invoice.registration.program:id,name', 'invoice.registration.branch:id,name');
    }

    /**
     * Ringkasan dan baris untuk layar dan PDF.
     * TODO(nanti): paginasi bila satu periode melewati beberapa ribu baris (JSON); export Excel sudah per potong.
     */
    public function summary(): array
    {
        $payments = $this->query()->orderBy('confirmed_at')->orderBy('id')->get();

        return [
            'period' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
            'total' => (float) $payments->sum('amount'),
            'count' => $payments->count(),
            'by_branch' => $this->group($payments, fn ($p) => $p->invoice->registration->branch_id, fn ($p) => $p->invoice->registration->branch?->name ?? 'Tanpa cabang', 'branch'),
            'by_program' => $this->group($payments, fn ($p) => $p->invoice->registration->program_id, fn ($p) => $p->invoice->registration->program?->name ?? 'Tanpa program', 'program'),
            'rows' => $payments->map(fn (Payment $p) => $this->row($p))->all(),
        ];
    }

    /** Pilihan filter: hanya cabang yang boleh diakses user. */
    public function options(): array
    {
        return [
            'branches' => Branch::whereIn('id', $this->user->accessibleBranchIds())->orderBy('name')->get(['id', 'name']),
            'programs' => Program::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function row(Payment $payment): array
    {
        $registration = $payment->invoice->registration;

        return [
            'id' => $payment->id,
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
            'invoice_number' => $payment->invoice->number,
            'student_name' => $registration->student_name,
            'parent_name' => $registration->parent_name,
            'program_name' => $registration->program?->name,
            'branch_name' => $registration->branch?->name,
            'method' => $payment->method,
            'amount' => (float) $payment->amount,
        ];
    }

    private function group(Collection $payments, callable $id, callable $name, string $key): array
    {
        return $payments->groupBy($id)->map(fn (Collection $group) => [
            "{$key}_id" => $id($group->first()),
            "{$key}_name" => $name($group->first()),
            'count' => $group->count(),
            'total' => (float) $group->sum('amount'),
        ])->sortByDesc('total')->values()->all();
    }
}
