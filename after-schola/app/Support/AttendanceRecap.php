<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun matriks rekap absensi murid meniru layout spreadsheet:
 * baris = murid (NAMA + KELAS asal), kolom = pertemuan (tanggal), sel = Hadir/Tidak,
 * plus baris "JUMLAH SISWA YANG HADIR" per pertemuan. Dikelompokkan per level (classroom).
 */
class AttendanceRecap
{
    public School $school;

    /** @var array<int, array{classroom: Classroom, sessions: Collection, students: Collection, present: array, present_totals: array}> */
    public array $groups = [];

    public ?Carbon $from = null;

    public ?Carbon $to = null;

    public function __construct(School $school, ?int $classroomId = null, ?string $from = null, ?string $to = null)
    {
        $this->school = $school;
        $this->from = $from ? Carbon::parse($from)->startOfDay() : null;
        $this->to = $to ? Carbon::parse($to)->endOfDay() : null;

        $classrooms = $school->classrooms()
            ->when($classroomId, fn ($q) => $q->where('id', $classroomId))
            ->orderBy('name')
            ->get();

        foreach ($classrooms as $classroom) {
            $sessions = ClassSession::where('classroom_id', $classroom->id)
                ->when($this->from, fn ($q) => $q->where('date', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->where('date', '<=', $this->to))
                ->orderBy('date')
                ->orderBy('meeting_no')
                ->get();

            $students = Student::where('classroom_id', $classroom->id)
                ->orderBy('name')
                ->get();

            // Peta kehadiran: [session_id][student_id] => bool
            $present = [];
            $sessionIds = $sessions->pluck('id');
            if ($sessionIds->isNotEmpty()) {
                StudentAttendance::whereIn('class_session_id', $sessionIds)
                    ->get(['class_session_id', 'student_id', 'is_present'])
                    ->each(function (StudentAttendance $att) use (&$present) {
                        $present[$att->class_session_id][$att->student_id] = (bool) $att->is_present;
                    });
            }

            // Total hadir per pertemuan.
            $presentTotals = [];
            foreach ($sessions as $session) {
                $presentTotals[$session->id] = collect($present[$session->id] ?? [])
                    ->filter()
                    ->count();
            }

            $this->groups[] = [
                'classroom' => $classroom,
                'sessions' => $sessions,
                'students' => $students,
                'present' => $present,
                'present_totals' => $presentTotals,
            ];
        }
    }

    public function periodLabel(): string
    {
        if ($this->from && $this->to) {
            return $this->from->format('d/m/Y').' – '.$this->to->format('d/m/Y');
        }
        if ($this->from) {
            return 'sejak '.$this->from->format('d/m/Y');
        }
        if ($this->to) {
            return 's/d '.$this->to->format('d/m/Y');
        }

        return 'Semua periode';
    }

    public function filenameBase(): string
    {
        $slug = str($this->school->name)->slug();
        $period = ($this->from || $this->to)
            ? '_'.optional($this->from)->format('Ymd').'-'.optional($this->to)->format('Ymd')
            : '';

        return "rekap-absensi_{$slug}{$period}";
    }
}
