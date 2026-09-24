<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSessionResource;
use App\Http\Resources\StudentGradeResource;
use App\Http\Resources\StudentResource;
use App\Models\ClassSession;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Http\Resources\StudentAttendanceResource;
use App\Models\School;
use App\Support\AttendanceRecap;
use Barryvdh\DomPDF\Facade\Pdf;

class SchoolDashboardController extends Controller
{
    public function summary(Request $request)
    {
        $schoolId = $request->user()->school_id;

        return response()->json([
            'students_count' => Student::where('school_id', $schoolId)->count(),
            'upcoming_sessions_count' => ClassSession::where('school_id', $schoolId)
                ->where('date', '>=', now()->toDateString())
                ->count(),
        ]);
    }

    public function students(Request $request)
    {
        $students = Student::where('school_id', $request->user()->school_id)
            ->with('classroom')
            ->orderBy('name')
            ->get();

        return StudentResource::collection($students);
    }

    public function showStudent(Request $request, Student $student)
    {
        abort_unless($student->school_id === $request->user()->school_id, 403);

        $student->load('classroom', 'attendances.classSession', 'grades');

        return response()->json([
            'student' => new StudentResource($student),
            'attendances' => StudentAttendanceResource::collection($student->attendances),
            'grades' => StudentGradeResource::collection($student->grades),
        ]);
    }

    public function schedule(Request $request)
    {
        $sessions = ClassSession::where('school_id', $request->user()->school_id)
            ->with('classroom', 'trainer')
            ->orderBy('date', 'desc')
            ->limit(100)
            ->get();

        return ClassSessionResource::collection($sessions);
    }

    public function export(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $school = School::findOrFail($user->school_id);

        $recap = new AttendanceRecap(
            $school,
            $data['classroom_id'] ?? null,
            $data['from'] ?? null,
            $data['to'] ?? null,
        );

        $pdf = Pdf::loadView('exports.attendance', ['recap' => $recap])
            ->setPaper('a4', 'landscape');

        return $pdf->download($recap->filenameBase() . '.pdf');
    }
}