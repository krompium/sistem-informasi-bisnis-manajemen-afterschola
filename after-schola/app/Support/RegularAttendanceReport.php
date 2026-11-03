<?php

namespace App\Support;

use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TrainerAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rekap dan monitoring absensi reguler (Modul 7c, MGT-40..43, KEU-08; sisi trainer 7d, TRN-11/12). Dipakai JSON,
 * Excel, dan PDF agar angkanya selalu sama. Scope data: 'branch' = cabang user (BR-19, Management/Keuangan),
 * 'own' = sesi yang dijalankan trainer itu sendiri, 'classes' = sesi kelas yang ditugaskan atau ia isi sebagai pengganti.
 * "Hari ini" mengikuti Asia/Jakarta.
 *
 * Aturan hitung rekap siswa: hanya sesi yang sudah lewat DAN sudah dicatat (dikunci atau punya baris absensi);
 * sesi yang trainer-nya belum mengabsen tidak menghukum siswa. Siswa dihitung pada sesi yang tanggalnya
 * dicakup enrollment-nya (BR-14). Rekap trainer menghitung semua sesi yang sudah mulai, termasuk yang tanpa check-in.
 * TODO(nanti): rekap dihitung di PHP untuk satu periode; paginasi/chunk bila periode lintas cabang terasa berat (NFR-02).
 */
class RegularAttendanceReport
{
    public const TRAINER_STATUS_LABELS = [
        'hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'tidak_hadir' => 'Tidak hadir', 'belum_check_in' => 'Belum check-in',
    ];

    public readonly string $from;

    public readonly string $to;

    /** @param array{from?: string, to?: string, branch_id?: ?int, class_group_id?: ?int, trainer_id?: ?int} $filters */
    public function __construct(private User $user, private array $filters = [], private string $scope = 'branch')
    {
        $this->from = $filters['from'] ?? $this->today();
        $this->to = $filters['to'] ?? $this->today();
    }

    /** Validasi periode dan filter dari request, lalu bentuk laporan. */
    public static function fromRequest(Request $request): self
    {
        return new self($request->user(), $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'class_group_id' => ['nullable', 'integer', 'exists:class_groups,id'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]));
    }

    /** Versi trainer (7d): validasi periode (+ filter kelas) lalu bentuk laporan ber-scope 'own' atau 'classes'. */
    public static function forTrainer(Request $request, string $scope): self
    {
        return new self($request->user(), $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'class_group_id' => ['nullable', 'integer', 'exists:class_groups,id'],
        ]), $scope);
    }

    public function filenameBase(string $kind): string
    {
        return "{$kind}-".str_replace('-', '', $this->from).'-'.str_replace('-', '', $this->to);
    }

    /** @return array{rows: list<array>, sessions: int} per (kelas, siswa) */
    public function studentRecap(): array
    {
        $sessions = $this->sessions()
            ->where(fn (Builder $q) => $q->where('is_locked', true)->orHas('studentAttendances'))
            ->with('studentAttendances', 'classGroup:id,name')->get();

        $enrollments = Enrollment::with('student:id,name')
            ->whereIn('class_group_id', $sessions->pluck('class_group_id')->unique())->get()->groupBy('class_group_id');

        $rows = [];
        foreach ($sessions as $session) {
            $marks = $session->studentAttendances->keyBy('student_id');
            foreach ($enrollments->get($session->class_group_id, []) as $enrollment) {
                if (! $enrollment->coversDate($session->date)) {
                    continue;
                }
                $key = $session->class_group_id.'-'.$enrollment->student_id;
                $rows[$key] ??= [
                    'class_group' => ['id' => $session->classGroup->id, 'name' => $session->classGroup->name],
                    'student' => ['id' => $enrollment->student->id, 'name' => $enrollment->student->name],
                    'sessions' => 0, 'present' => 0,
                ];
                $rows[$key]['sessions']++;
                $rows[$key]['present'] += $marks->get($enrollment->student_id)?->is_present ? 1 : 0;
            }
        }

        $rows = collect($rows)->map(fn (array $r) => $r + [
            'absent' => $r['sessions'] - $r['present'],
            'percentage' => round($r['present'] / $r['sessions'] * 100, 1),
        ])->sortBy([['class_group.name', 'asc'], ['student.name', 'asc']])->values()->all();

        return ['rows' => $rows, 'sessions' => $sessions->count()];
    }

    /** Riwayat satu siswa: tiap sesi yang sudah lewat pada saat ia terdaftar, dengan status hadir/tidak_hadir/belum_dicatat. */
    public function studentHistory(Student $student): Collection
    {
        $enrollments = Enrollment::where('student_id', $student->id)->get()->groupBy('class_group_id');

        return $this->sessions()->whereIn('class_group_id', $enrollments->keys())
            ->with('studentAttendances', 'classGroup:id,name')->orderBy('date')->orderBy('start_time')->get()
            ->filter(fn (ClassSession $s) => $enrollments[$s->class_group_id]->contains(fn (Enrollment $e) => $e->coversDate($s->date)))
            ->map(function (ClassSession $s) use ($student) {
                $mark = $s->studentAttendances->firstWhere('student_id', $student->id);
                $recorded = $s->is_locked || $s->studentAttendances->isNotEmpty();

                return [
                    'session_id' => $s->id,
                    'date' => $s->date->toDateString(),
                    'start_time' => substr($s->start_time, 0, 5),
                    'end_time' => substr($s->end_time, 0, 5),
                    'meeting_no' => $s->meeting_no,
                    'class_group' => ['id' => $s->classGroup->id, 'name' => $s->classGroup->name],
                    'status' => ! $recorded ? 'belum_dicatat' : ($mark?->is_present ? 'hadir' : 'tidak_hadir'),
                    'note' => $mark?->note,
                ];
            })->values();
    }

    /** Satu baris per sesi yang sudah mulai: pelaksana, status check-in (atau belum_check_in), jam, foto, GPS. */
    public function trainerRows(): Collection
    {
        return $this->sessions(startedOnly: true)
            ->when($this->filters['trainer_id'] ?? null, fn (Builder $q, $id) => $q->where('trainer_id', $id))
            ->with('classGroup:id,name,branch_id', 'classGroup.branch:id,name', 'trainer:id,name', 'trainerAttendances')
            ->orderBy('date')->orderBy('start_time')->orderBy('id')->get()
            ->map(fn (ClassSession $s) => self::trainerRow($s));
    }

    /** TRN-12: riwayat absensi siswa per sesi (terbaru dulu) pada kelas yang boleh dilihat trainer. */
    public function classHistory(): Collection
    {
        $sessions = $this->sessions()->with('studentAttendances', 'classGroup:id,name', 'trainer:id,name')
            ->orderByDesc('date')->orderByDesc('start_time')->orderByDesc('id')->get();
        $enrollments = Enrollment::whereIn('class_group_id', $sessions->pluck('class_group_id')->unique())->get()->groupBy('class_group_id');

        return $sessions->map(fn (ClassSession $s) => [
            'session_id' => $s->id,
            'date' => $s->date->toDateString(),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'meeting_no' => $s->meeting_no,
            'class_group' => ['id' => $s->classGroup->id, 'name' => $s->classGroup->name],
            'trainer' => ['id' => $s->trainer->id, 'name' => $s->trainer->name],
            'is_locked' => $s->is_locked,
            'status' => $s->is_locked || $s->studentAttendances->isNotEmpty() ? 'dicatat' : 'belum_dicatat',
            'present_count' => $s->studentAttendances->where('is_present', true)->count(),
            'total' => $enrollments->get($s->class_group_id, collect())->filter(fn (Enrollment $e) => $e->coversDate($s->date))->count(),
        ])->values();
    }

    /** @return Collection<int, array> ringkasan per trainer dari hasil trainerRows() */
    public function trainerSummary(Collection $rows): Collection
    {
        return $rows->groupBy('trainer.id')->map(fn (Collection $group) => [
            'trainer' => $group->first()['trainer'],
            'sessions' => $group->count(),
            'hadir' => $group->where('status', 'hadir')->count(),
            'terlambat' => $group->where('status', 'terlambat')->count(),
            'tidak_hadir' => $group->where('status', 'tidak_hadir')->count(),
            'belum_check_in' => $group->where('status', 'belum_check_in')->count(),
        ])->sortBy('trainer.name')->values();
    }

    /** MGT-43: sesi hari ini yang sudah mulai tetapi pelaksananya belum check-in. */
    public static function missingToday(User $user): Collection
    {
        $today = now(config('app.display_timezone'))->toDateString();

        return (new self($user, ['from' => $today, 'to' => $today]))->trainerRows()
            ->where('status', 'belum_check_in')->values();
    }

    public static function trainerRow(ClassSession $s): array
    {
        $attendance = $s->trainerAttendances->firstWhere('trainer_id', $s->trainer_id);

        return [
            'session_id' => $s->id,
            'date' => $s->date->toDateString(),
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'class_group' => ['id' => $s->classGroup->id, 'name' => $s->classGroup->name],
            'branch' => $s->classGroup->branch ? ['id' => $s->classGroup->branch->id, 'name' => $s->classGroup->branch->name] : null,
            'trainer' => ['id' => $s->trainer->id, 'name' => $s->trainer->name],
            'status' => $attendance?->status ?? 'belum_check_in',
            'check_in_at' => $attendance?->check_in_at,
            'photo_url' => $attendance?->photo_path ? asset('storage/'.$attendance->photo_path) : null,
            'latitude' => $attendance?->latitude,
            'longitude' => $attendance?->longitude,
            'note' => $attendance?->note,
        ];
    }

    /**
     * Sesi reguler dalam periode, kelas dalam cabang user, dan sudah lewat (tanggal <= hari ini).
     * $startedOnly: sesi hari ini yang jam mulainya belum tiba dibuang (di PHP, karena format kolom time beda per database).
     */
    private function sessions(bool $startedOnly = false): Builder
    {
        $today = $this->today();
        $to = min($this->to, $today);

        $query = ClassSession::query()->whereNotNull('class_sessions.class_group_id')
            ->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $to);

        $query = match ($this->scope) {
            'own' => $query->where('class_sessions.trainer_id', $this->user->id),
            'classes' => $query->visibleToRegular($this->user),
            default => $query->whereHas('classGroup', fn (Builder $group) => $group->inBranchesOf($this->user)
                ->when($this->filters['branch_id'] ?? null, fn (Builder $q, $id) => $q->where('branch_id', $id))),
        };
        $query->when($this->filters['class_group_id'] ?? null, fn (Builder $q, $id) => $q->where('class_sessions.class_group_id', $id));

        if ($startedOnly) {
            $nowTime = now(config('app.display_timezone'))->format('H:i');
            // Sesi hari ini yang belum mulai: tidak ada nilai start_time yang bisa dibandingkan lintas format,
            // jadi ambil id-nya dulu lalu kecualikan.
            $notStarted = (clone $query)->whereDate('date', $today)->get(['id', 'start_time'])
                ->filter(fn (ClassSession $s) => substr($s->start_time, 0, 5) > $nowTime)->pluck('id');
            $query->whereNotIn('class_sessions.id', $notStarted);
        }

        return $query;
    }

    private function today(): string
    {
        return now(config('app.display_timezone'))->toDateString();
    }
}
