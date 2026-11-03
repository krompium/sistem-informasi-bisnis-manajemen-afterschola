<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Absensi siswa sesi kelas reguler (Modul 7a, TRN-06). Roster = siswa yang enrollment-nya mencakup
 * tanggal sesi (BR-14). Hadir/tidak (boolean, A-06), unik per (sesi, siswa) (BR-13), terkunci = 422 (AC-11).
 * Absensi sekolah tetap di StudentAttendanceController (/sessions/...).
 */
class ClassSessionAttendanceController extends Controller
{
    public function index(ClassSession $classSession)
    {
        $this->authorize('viewAny', ClassGroup::class);
        $this->authorize('viewRegular', $classSession);

        return $this->roster($classSession);
    }

    public function sync(Request $request, ClassSession $classSession)
    {
        $this->authorize('inputRegularAttendance', $classSession);

        if ($classSession->is_locked) {
            throw ValidationException::withMessages(['session' => ['Sesi sudah dikunci; absensi tidak dapat diubah.']]);
        }

        $data = $request->validate([
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'attendances.*.is_present' => ['required', 'boolean'],
            'attendances.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        // Semua baris diperiksa dulu: satu siswa di luar roster membatalkan seluruh permintaan.
        $rosterIds = $this->rosterQuery($classSession)->pluck('students.id')->all();
        foreach ($data['attendances'] as $row) {
            if (! in_array($row['student_id'], $rosterIds, true)) {
                throw ValidationException::withMessages([
                    'attendances' => ["Siswa #{$row['student_id']} tidak terdaftar di kelas ini pada tanggal sesi."],
                ]);
            }
        }

        DB::transaction(function () use ($classSession, $data) {
            foreach ($data['attendances'] as $row) {
                StudentAttendance::updateOrCreate(
                    ['class_session_id' => $classSession->id, 'student_id' => $row['student_id']],
                    ['is_present' => $row['is_present'], 'note' => $row['note'] ?? null],
                );
            }
        });

        return $this->roster($classSession);
    }

    private function rosterQuery(ClassSession $session): Builder
    {
        return Student::whereHas('enrollments', fn (Builder $enrollment) => $enrollment
            ->where('class_group_id', $session->class_group_id)->activeOn($session->date))
            ->orderBy('name');
    }

    private function roster(ClassSession $session): JsonResponse
    {
        $marks = $session->studentAttendances()->get()->keyBy('student_id');

        $rows = $this->rosterQuery($session)->get(['students.id', 'students.name'])->map(fn (Student $student) => [
            'student_id' => $student->id,
            'name' => $student->name,
            'is_present' => $marks->get($student->id)?->is_present ?? false,
            'note' => $marks->get($student->id)?->note,
        ])->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'session_id' => $session->id,
                'is_locked' => $session->is_locked,
                'present_count' => $rows->where('is_present', true)->count(),
                'total' => $rows->count(),
            ],
        ]);
    }
}
