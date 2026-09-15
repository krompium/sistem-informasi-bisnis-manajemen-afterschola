<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentAttendanceController extends Controller
{
    /**
     * Roster murid pada level pertemuan ini + status kehadirannya.
     */
    public function index(ClassSession $classSession)
    {
        $this->authorize('view', $classSession);

        $existing = $classSession->studentAttendances()->get()->keyBy('student_id');

        $students = Student::where('classroom_id', $classSession->classroom_id)
            ->orderBy('name')
            ->get();

        $roster = $students->map(function (Student $student) use ($existing) {
            $att = $existing->get($student->id);

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'origin_class' => $student->origin_class,
                'is_present' => $att?->is_present ?? false,
                'note' => $att?->note,
            ];
        });

        return response()->json([
            'session_id' => $classSession->id,
            'is_locked' => $classSession->is_locked,
            'present_count' => $roster->where('is_present', true)->count(),
            'total' => $roster->count(),
            'data' => $roster->values(),
        ]);
    }

    /**
     * Simpan absensi murid (bulk upsert) selama pertemuan belum dikunci.
     */
    public function sync(Request $request, ClassSession $classSession)
    {
        $this->authorize('inputAttendance', $classSession);

        $data = $request->validate([
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'attendances.*.is_present' => ['required', 'boolean'],
            'attendances.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        // Semua murid harus milik level pertemuan ini.
        $validIds = Student::where('classroom_id', $classSession->classroom_id)->pluck('id')->all();

        foreach ($data['attendances'] as $row) {
            if (! in_array($row['student_id'], $validIds, true)) {
                throw ValidationException::withMessages([
                    'attendances' => ["Murid #{$row['student_id']} bukan bagian dari level pertemuan ini."],
                ]);
            }

            StudentAttendance::updateOrCreate(
                ['class_session_id' => $classSession->id, 'student_id' => $row['student_id']],
                ['is_present' => $row['is_present'], 'note' => $row['note'] ?? null],
            );
        }

        $presentCount = $classSession->studentAttendances()->where('is_present', true)->count();

        return response()->json([
            'message' => 'Absensi murid tersimpan.',
            'present_count' => $presentCount,
        ]);
    }
}
