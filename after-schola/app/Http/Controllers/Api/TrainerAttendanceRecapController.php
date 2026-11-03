<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\RegularAttendanceReport;
use Illuminate\Http\Request;

/**
 * Sisi trainer untuk absensi reguler (Modul 7d). Role trainer di route; data dibatasi ke sesi yang ia jalankan
 * (rekap) atau kelas yang ia pegang (riwayat) di RegularAttendanceReport. Export Excel: RegularAttendanceExportController.
 */
class TrainerAttendanceRecapController extends Controller
{
    /** TRN-11: rekap kehadiran trainer sendiri per periode. */
    public function recap(Request $request)
    {
        $report = RegularAttendanceReport::forTrainer($request, 'own');
        $rows = $report->trainerRows();

        return response()->json(['data' => $rows, 'summary' => $report->trainerSummary($rows)]);
    }

    /** TRN-12: riwayat absensi siswa pada kelas saya. */
    public function classHistory(Request $request)
    {
        return response()->json(['data' => RegularAttendanceReport::forTrainer($request, 'classes')->classHistory()]);
    }
}
