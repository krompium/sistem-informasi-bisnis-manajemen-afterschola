<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\RegularAttendanceReport;
use Illuminate\Http\Request;

/**
 * Rekap dan monitoring absensi reguler (Modul 7c, MGT-40..43). Permission di route; scope cabang di RegularAttendanceReport.
 * Export Excel/PDF ada di RegularAttendanceExportController.
 */
class RegularAttendanceReportController extends Controller
{
    /** MGT-40: rekap kehadiran siswa per kelas dan periode. */
    public function recap(Request $request)
    {
        $report = RegularAttendanceReport::fromRequest($request);
        $recap = $report->studentRecap();

        return response()->json([
            'data' => $recap['rows'],
            'meta' => ['sessions' => $recap['sessions'], 'from' => $report->from, 'to' => $report->to],
        ]);
    }

    /** MGT-41: riwayat absensi satu siswa. */
    public function student(Request $request, Student $student)
    {
        $this->authorize('viewRegular', $student);

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->name],
            'data' => RegularAttendanceReport::fromRequest($request)->studentHistory($student),
        ]);
    }

    /** MGT-42 / KEU-08: tiap sesi dengan status check-in pelaksana, plus ringkasan per trainer. */
    public function trainers(Request $request)
    {
        $report = RegularAttendanceReport::fromRequest($request);
        $rows = $report->trainerRows();

        return response()->json(['data' => $rows, 'summary' => $report->trainerSummary($rows)]);
    }

    /** MGT-43: trainer yang belum check-in pada sesi hari ini (yang sudah mulai). */
    public function missingToday(Request $request)
    {
        return response()->json(['data' => RegularAttendanceReport::missingToday($request->user())]);
    }
}
