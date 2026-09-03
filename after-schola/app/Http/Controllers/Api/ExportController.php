<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\AttendanceRecap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Export rekap absensi murid ke Excel (meniru layout spreadsheet).
     */
    public function attendanceExcel(Request $request): StreamedResponse
    {
        $recap = $this->buildRecap($request);

        return response()->streamDownload(function () use ($recap) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues(['REKAP ABSENSI - '.$recap->school->name]));
            $writer->addRow(Row::fromValues(['Periode: '.$recap->periodLabel()]));
            $writer->addRow(Row::fromValues(['']));

            foreach ($recap->groups as $group) {
                $sessions = $group['sessions'];

                $writer->addRow(Row::fromValues(['LEVEL: '.$group['classroom']->name]));

                // Header kolom: NO | NAMA | KELAS | P1 (tgl) | P2 (tgl) | ...
                $head = ['NO', 'NAMA', 'KELAS'];
                foreach ($sessions as $session) {
                    $head[] = 'P'.$session->meeting_no.' ('.$session->date->format('d/m').')';
                }
                $writer->addRow(Row::fromValues($head));

                // Baris murid.
                $no = 1;
                foreach ($group['students'] as $student) {
                    $line = [$no++, $student->name, $student->origin_class ?? ''];
                    foreach ($sessions as $session) {
                        $isPresent = $group['present'][$session->id][$student->id] ?? false;
                        $line[] = $isPresent ? 'Hadir' : 'Tidak';
                    }
                    $writer->addRow(Row::fromValues($line));
                }

                // Baris jumlah hadir.
                $totals = ['', 'JUMLAH SISWA YANG HADIR', ''];
                foreach ($sessions as $session) {
                    $totals[] = $group['present_totals'][$session->id] ?? 0;
                }
                $writer->addRow(Row::fromValues($totals));
                $writer->addRow(Row::fromValues(['']));
            }

            $writer->close();
        }, $recap->filenameBase().'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export rekap absensi murid ke PDF siap cetak (kop + periode).
     */
    public function attendancePdf(Request $request)
    {
        $recap = $this->buildRecap($request);

        $pdf = Pdf::loadView('exports.attendance', ['recap' => $recap])
            ->setPaper('a4', 'landscape');

        return $pdf->download($recap->filenameBase().'.pdf');
    }

    private function buildRecap(Request $request): AttendanceRecap
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $school = School::findOrFail($data['school_id']);

        // Isolasi data + hak export.
        $this->authorize('view', $school);
        abort_unless($request->user()->can('export data'), 403, 'Tidak punya izin export.');

        return new AttendanceRecap(
            $school,
            $data['classroom_id'] ?? null,
            $data['from'] ?? null,
            $data['to'] ?? null,
        );
    }
}
