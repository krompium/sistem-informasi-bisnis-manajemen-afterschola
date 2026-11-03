<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\RegularAttendanceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** Export absensi reguler (Modul 7c). Angka sama dengan JSON karena memakai RegularAttendanceReport yang sama. */
class RegularAttendanceExportController extends Controller
{
    private const XLSX = ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    public function studentExcel(Request $request)
    {
        $report = RegularAttendanceReport::fromRequest($request);
        $rows = $report->studentRecap()['rows'];

        return $this->xlsx($report->filenameBase('rekap-absensi-siswa'), ['Kelas', 'Siswa', 'Pertemuan Tercatat', 'Hadir', 'Tidak Hadir', 'Kehadiran (%)'],
            array_map(fn (array $r) => [$r['class_group']['name'], $r['student']['name'], $r['sessions'], $r['present'], $r['absent'], $r['percentage']], $rows));
    }

    public function studentPdf(Request $request)
    {
        $report = RegularAttendanceReport::fromRequest($request);

        return Pdf::loadView('exports.regular_attendance', ['report' => $report, 'recap' => $report->studentRecap()])
            ->setPaper('a4', 'portrait')
            ->download($report->filenameBase('rekap-absensi-siswa').'.pdf');
    }

    /** KEU-08 (bagian Excel): rekap absensi trainer untuk honor; sesi tanpa check-in ikut sebagai "Belum check-in". */
    public function trainerExcel(Request $request)
    {
        $report = RegularAttendanceReport::fromRequest($request);
        $zone = config('app.display_timezone');

        return $this->xlsx($report->filenameBase('rekap-absensi-trainer'),
            ['Tanggal', 'Jam', 'Kelas', 'Cabang', 'Trainer', 'Status', 'Waktu Check-in', 'GPS', 'Catatan'],
            $report->trainerRows()->map(fn (array $r) => [
                Carbon::parse($r['date'])->format('d/m/Y'),
                "{$r['start_time']}–{$r['end_time']}",
                $r['class_group']['name'],
                $r['branch']['name'] ?? '',
                $r['trainer']['name'],
                RegularAttendanceReport::TRAINER_STATUS_LABELS[$r['status']],
                $r['check_in_at'] ? $r['check_in_at']->copy()->timezone($zone)->format('d/m/Y H:i') : '',
                $r['latitude'] !== null ? "{$r['latitude']}, {$r['longitude']}" : '',
                $r['note'] ?? '',
            ])->all());
    }

    private function xlsx(string $filename, array $header, array $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $writer = new Writer;
            $writer->getOptions()->setColumnWidthForRange(22, 1, count($header));
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle($header, (new Style)->withFontBold(true)->withBackgroundColor('92D050')));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, "{$filename}.xlsx", self::XLSX);
    }
}
