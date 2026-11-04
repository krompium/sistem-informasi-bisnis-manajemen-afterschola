<?php

namespace App\Support;

use App\Http\Requests\IncomeReportRequest;
use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\EnrollmentDecision;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\StudentAttendance;
use App\Models\TrialEvaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Angka dashboard Management jalur reguler (MGT-60 sampai 62; rumus KPI di PRD 6.3).
 * Periode dan batas hari memakai aturan yang sama dengan laporan pemasukan (IncomeReport, zona tampilan);
 * income memakai query IncomeReport apa adanya. Semua angka dibatasi scope cabang user (BR-19) dan
 * filter cabang opsional. Funnel menghitung tiap tahap menurut tanggal kejadiannya sendiri dalam periode (bukan kohort).
 * TODO(nanti): funnel kohort (nasib calon yang masuk di periode) bila Management memintanya.
 */
class DashboardMetrics
{
    private readonly IncomeReport $income;

    public function __construct(private User $user, ?string $from, ?string $to, private ?int $branchId = null)
    {
        $this->income = new IncomeReport($user, $from, $to, $branchId);
    }

    public static function fromRequest(IncomeReportRequest $request): self
    {
        return new self($request->user(), $request->input('from'), $request->input('to'), $request->integer('branch_id') ?: null);
    }

    public function kpi(): array
    {
        $end = $this->income->to->toDateString();
        $activeAtEnd = fn (): Builder => $this->enrollments()
            ->whereDate('enrollments.start_date', '<=', $end)
            ->where(fn (Builder $q) => $q->whereNull('enrollments.end_date')->orWhereDate('enrollments.end_date', '>=', $end));

        $activeClasses = fn (): Builder => $this->inBranch(ClassGroup::query()->where('class_groups.is_active', true), 'class_groups.branch_id');
        $occupied = $activeAtEnd()->whereIn('enrollments.class_group_id', $activeClasses()->select('class_groups.id'))->count();
        $capacity = (int) $activeClasses()->sum('class_groups.capacity');

        // Retensi: siswa yang enrollment-nya berakhir di periode dan punya perpanjangan lunas, dibagi semua siswa yang berakhir di periode.
        $endedStudents = $this->enrollments()
            ->whereBetween('enrollments.end_date', [$this->income->from->toDateString(), $end])
            ->pluck('enrollments.student_id')->unique();
        $renewed = Registration::query()->where('type', 'perpanjangan')
            ->whereIn('student_id', $endedStudents)
            ->whereHas('invoices', fn (Builder $invoice) => $invoice->where('status', 'paid'))
            ->pluck('student_id')->unique()->count();

        return [
            'period' => ['from' => $this->income->from->toDateString(), 'to' => $end],
            'branch_id' => $this->branchId,
            'branches' => Branch::visibleTo($this->user)->orderBy('name')->get(['id', 'name']),
            'students_active' => ['value' => $activeAtEnd()->distinct()->count('enrollments.student_id')],
            'income' => ['value' => (float) $this->income->query()->sum('amount')],
            'retention' => ['value' => $this->percent($renewed, $endedStudents->count()), 'numerator' => $renewed, 'denominator' => $endedStudents->count()],
            'occupancy' => ['value' => $this->percent($occupied, $capacity), 'numerator' => $occupied, 'denominator' => $capacity],
        ];
    }

    public function funnel(): array
    {
        $fromUtc = $this->income->from->copy()->utc();
        $toUtc = $this->income->to->copy()->utc();
        $dates = [$this->income->from->toDateString(), $this->income->to->toDateString()];

        $stages = [
            'calon' => ['Calon baru', $this->inBranch(Lead::query(), 'leads.branch_id')->whereBetween('leads.created_at', [$fromUtc, $toUtc])->count()],
            'trial_hadir' => ['Trial hadir', $this->inBranch(TrialEvaluation::query(), 'trial_evaluations.branch_id')
                ->where('attendance', 'hadir')->whereBetween('trial_date', $dates)->count()],
            'lanjut' => ['Memutuskan lanjut', EnrollmentDecision::query()->where('decision', 'lanjut')->whereBetween('decided_at', $dates)
                ->whereHas('evaluation', fn (Builder $evaluation) => $this->inBranch($evaluation, 'trial_evaluations.branch_id'))->count()],
            'terverifikasi' => ['Registrasi terverifikasi', $this->registrations()->where('type', 'baru')->where('status', 'terverifikasi')
                ->whereBetween('verified_at', [$fromUtc, $toUtc])->count()],
            'lunas' => ['Lunas', Invoice::query()->where('status', 'paid')->whereBetween('paid_at', [$fromUtc, $toUtc])
                ->whereHas('registration', fn (Builder $registration) => $this->inBranch($registration->where('type', 'baru'), 'registrations.branch_id'))->count()],
            'ditempatkan' => ['Ditempatkan di kelas', $this->enrollments()->whereBetween('enrollments.start_date', $dates)->count()],
        ];

        return ['period' => ['from' => $dates[0], 'to' => $dates[1]], 'stages' => collect($stages)
            ->map(fn (array $stage, string $key) => ['key' => $key, 'label' => $stage[0], 'count' => $stage[1]])->values()->all()];
    }

    /** Panel operasional hari ini (MGT-62). Hari = hari di zona tampilan; tidak bergantung pada periode. */
    public function panel(): array
    {
        $today = Carbon::now(config('app.display_timezone'))->toDateString();
        $groupIds = fn (): Builder => $this->inBranch(ClassGroup::query(), 'class_groups.branch_id')->select('class_groups.id');
        $inScope = fn (Builder $registration) => $this->inBranch($registration, 'registrations.branch_id');

        return [
            'date' => $today,
            'sessions_today' => ClassSession::query()->whereDate('class_sessions.date', $today)->whereIn('class_sessions.class_group_id', $groupIds())->count(),
            'students_present_today' => StudentAttendance::query()->where('is_present', true)
                ->whereHas('classSession', fn (Builder $session) => $session->whereDate('class_sessions.date', $today)->whereIn('class_sessions.class_group_id', $groupIds()))->count(),
            'registrations_waiting' => $this->registrations()->where('status', 'menunggu_verifikasi')->count(),
            'payments_waiting' => Payment::query()->where('status', 'pending')->whereHas('invoice.registration', $inScope)->count(),
            'invoices_overdue' => Invoice::query()->where('status', 'unpaid')->whereDate('due_date', '<', $today)->whereHas('registration', $inScope)->count(),
        ];
    }

    private function registrations(): Builder
    {
        return $this->inBranch(Registration::query(), 'registrations.branch_id');
    }

    private function enrollments(): Builder
    {
        return Enrollment::query()->whereIn(
            'enrollments.class_group_id',
            $this->inBranch(ClassGroup::query(), 'class_groups.branch_id')->select('class_groups.id')
        );
    }

    /** Batas cabang: scope user (BR-19) dan filter cabang opsional. Cabang di luar scope menghasilkan 0, bukan data. */
    private function inBranch(Builder $query, string $column): Builder
    {
        return $query
            ->when(! $this->user->hasAllBranches(), fn (Builder $q) => $q->whereIn($column, $this->user->accessibleBranchIds()))
            ->when($this->branchId, fn (Builder $q, int $id) => $q->where($column, $id));
    }

    private function percent(int|float $numerator, int|float $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
    }
}
