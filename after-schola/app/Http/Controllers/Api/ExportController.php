<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpoReport;
use App\Models\School;
use App\Support\AttendanceRecap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
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
            // ----- Gaya sel (meniru tampilan spreadsheet) -----
            $border = new Border(
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::TOP, Color::rgb(150, 150, 150)),
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::BOTTOM, Color::rgb(150, 150, 150)),
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::LEFT, Color::rgb(150, 150, 150)),
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::RIGHT, Color::rgb(150, 150, 150)),
            );
            $titleStyle = (new Style)->withFontBold(true)->withFontSize(14);
            $schoolStyle = (new Style)->withFontBold(true)->withFontSize(11);
            $metaStyle = (new Style)->withFontSize(10)->withFontColor('555555');
            $levelStyle = (new Style)->withFontBold(true)->withFontSize(11)->withFontColor('0B2A5B');
            $headGreen = (new Style)->withFontBold(true)->withBackgroundColor('92D050')
                ->withBorder($border)->withCellAlignment(CellAlignment::CENTER)
                ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $center = (new Style)->withBorder($border)->withCellAlignment(CellAlignment::CENTER);
            $left = (new Style)->withBorder($border)->withCellAlignment(CellAlignment::LEFT);
            $check = (new Style)->withBorder($border)->withCellAlignment(CellAlignment::CENTER)
                ->withFontColor('0B7D24')->withFontBold(true);
            $totalCenter = (new Style)->withFontBold(true)->withBackgroundColor('E2EFDA')
                ->withBorder($border)->withCellAlignment(CellAlignment::CENTER);
            $totalLabel = (new Style)->withFontBold(true)->withBackgroundColor('E2EFDA')
                ->withBorder($border)->withCellAlignment(CellAlignment::LEFT);

            $maxSessions = 0;
            foreach ($recap->groups as $g) {
                $maxSessions = max($maxSessions, $g['sessions']->count());
            }

            $writer = new Writer;
            $options = $writer->getOptions();
            $options->setColumnWidth(5, 1);   // NO
            $options->setColumnWidth(28, 2);  // NAMA
            $options->setColumnWidth(9, 3);   // KELAS
            if ($maxSessions > 0) {
                $options->setColumnWidthForRange(11, 4, 3 + $maxSessions);
            }
            $writer->openToFile('php://output');

            // ----- Kop -----
            $writer->addRow(Row::fromValuesWithStyle(['REKAP ABSENSI MURID'], $titleStyle));
            $writer->addRow(Row::fromValuesWithStyle(['Sekolah: '.$recap->school->name], $schoolStyle));
            $writer->addRow(Row::fromValuesWithStyle(['Periode: '.$recap->periodLabel()], $metaStyle));
            $writer->addRow(Row::fromValues(['']));

            foreach ($recap->groups as $group) {
                $sessions = $group['sessions'];
                $n = $sessions->count();

                $writer->addRow(Row::fromValuesWithStyle(['Level: '.$group['classroom']->name], $levelStyle));

                // Header baris 1: NO | NAMA | KELAS | 1 | 2 | ...
                $headNo = ['NO', 'NAMA', 'KELAS'];
                foreach ($sessions as $session) {
                    $headNo[] = $session->meeting_no;
                }
                $writer->addRow(Row::fromValuesWithStyle($headNo, $headGreen));

                // Header baris 2: (kosong) | (kosong) | (kosong) | tanggal | tanggal | ...
                $headDate = ['', '', ''];
                foreach ($sessions as $session) {
                    $headDate[] = $session->date->format('n/j/Y');
                }
                $writer->addRow(Row::fromValuesWithStyle($headDate, $headGreen));

                // Baris murid: centang (✓) untuk hadir, kosong untuk tidak hadir.
                $no = 1;
                foreach ($group['students'] as $student) {
                    $line = [$no++, $student->name, $student->origin_class ?? ''];
                    $styles = [$center, $left, $center];
                    foreach ($sessions as $session) {
                        $isPresent = $group['present'][$session->id][$student->id] ?? false;
                        $line[] = $isPresent ? '✓' : '';
                        $styles[] = $check;
                    }
                    $writer->addRow(Row::fromValuesWithStyles($line, $styles));
                }

                // Baris jumlah hadir.
                $totals = ['', 'JUMLAH SISWA YANG HADIR', ''];
                $totalStyles = [$totalCenter, $totalLabel, $totalCenter];
                foreach ($sessions as $session) {
                    $totals[] = $group['present_totals'][$session->id] ?? 0;
                    $totalStyles[] = $totalCenter;
                }
                $writer->addRow(Row::fromValuesWithStyles($totals, $totalStyles));
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

    /**
     * Rekap absensi murid dalam bentuk JSON (untuk ditampilkan di layar).
     * Cukup izin melihat sekolah (isolasi data trainer) — tanpa perlu izin export.
     */
    public function attendanceRecap(Request $request)
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $school = School::findOrFail($data['school_id']);
        $this->authorize('view', $school);

        $recap = new AttendanceRecap(
            $school,
            $data['classroom_id'] ?? null,
            $data['from'] ?? null,
            $data['to'] ?? null,
        );

        return response()->json([
            'school' => ['id' => $recap->school->id, 'name' => $recap->school->name],
            'period' => $recap->periodLabel(),
            'can_export' => (bool) $request->user()->can('export data'),
            'groups' => array_map(function (array $g) {
                return [
                    'classroom' => [
                        'id' => $g['classroom']->id,
                        'name' => $g['classroom']->name,
                        'level' => $g['classroom']->level,
                    ],
                    'sessions' => $g['sessions']->map(fn ($s) => [
                        'id' => $s->id,
                        'meeting_no' => $s->meeting_no,
                        'date' => $s->date->toDateString(),
                        'mode' => $s->mode,
                        'is_locked' => (bool) $s->is_locked,
                    ])->values(),
                    'students' => $g['students']->map(fn ($st) => [
                        'id' => $st->id,
                        'name' => $st->name,
                        'origin_class' => $st->origin_class,
                    ])->values(),
                    'present' => $g['present'],
                    'present_totals' => $g['present_totals'],
                ];
            }, $recap->groups),
        ]);
    }

    /**
     * Export laporan ekspo ke Excel (meniru sheet Google Form).
     */
    public function expoExcel(Request $request): StreamedResponse
    {
        $reports = $this->expoQuery($request);

        return response()->streamDownload(function () use ($reports) {
            $border = new Border(
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::TOP, Color::rgb(150, 150, 150)),
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::BOTTOM, Color::rgb(150, 150, 150)),
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::LEFT, Color::rgb(150, 150, 150)),
                new BorderPart(\OpenSpout\Common\Entity\Style\BorderName::RIGHT, Color::rgb(150, 150, 150)),
            );
            $head = (new Style)->withFontBold(true)->withBackgroundColor('92D050')
                ->withBorder($border)->withCellAlignment(CellAlignment::CENTER)
                ->withCellVerticalAlignment(CellVerticalAlignment::CENTER)->withShouldWrapText(true);
            $cell = (new Style)->withBorder($border)->withCellVerticalAlignment(CellVerticalAlignment::TOP)->withShouldWrapText(true);
            $center = (new Style)->withBorder($border)->withCellAlignment(CellAlignment::CENTER)->withCellVerticalAlignment(CellVerticalAlignment::TOP);

            $writer = new Writer;
            $o = $writer->getOptions();
            $o->setColumnWidth(4, 1);
            $o->setColumnWidth(18, 2);
            $o->setColumnWidth(16, 3);
            $o->setColumnWidth(24, 4);
            $o->setColumnWidth(22, 5);
            $o->setColumnWidth(8, 6);
            $o->setColumnWidth(14, 7);
            $o->setColumnWidth(18, 8);
            $o->setColumnWidth(12, 9);
            $o->setColumnWidth(30, 10);
            $o->setColumnWidth(40, 11);
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(['LAPORAN EKSPO / FREE-TRIAL — After Schola'],
                (new Style)->withFontBold(true)->withFontSize(14)));
            $writer->addRow(Row::fromValues(['']));

            $header = [
                'NO', 'Timestamp', 'Tanggal Pelaksanaan', 'Nama Sekolah', 'Nama Tim',
                'Rating (1-5)', 'Sesuai Jadwal', 'Antusiasme Siswa', 'Ada Kendala',
                'Penjelasan Kendala', 'Dokumentasi (foto/link)',
            ];
            $writer->addRow(Row::fromValuesWithStyle($header, $head));

            $no = 1;
            foreach ($reports as $r) {
                $docs = collect($r->photo_paths ?? [])->map(fn ($p) => asset('storage/'.$p));
                if ($r->doc_url) {
                    $docs->push($r->doc_url);
                }

                $line = [
                    $no++,
                    optional($r->created_at)->format('d/m/Y H:i'),
                    optional($r->date)->format('d/m/Y'),
                    $r->school->name ?? '',
                    $r->team_name ?? '',
                    $r->rating ?? '',
                    $r->on_schedule ? ucfirst($r->on_schedule) : '',
                    $r->enthusiasm ?? '',
                    $r->has_issue ? 'Ya' : 'Tidak',
                    $r->issue_note ?? '',
                    $docs->implode("\n"),
                ];
                $styles = [$center, $center, $center, $cell, $cell, $center, $center, $cell, $center, $cell, $cell];
                $writer->addRow(Row::fromValuesWithStyles($line, $styles));
            }

            $writer->close();
        }, 'laporan-ekspo.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export laporan ekspo ke PDF (dengan foto dokumentasi ter-embed).
     */
    public function expoPdf(Request $request)
    {
        $reports = $this->expoQuery($request);

        $items = $reports->map(function (ExpoReport $r) {
            $photos = collect($r->photo_paths ?? [])->map(function ($p) {
                $full = storage_path('app/public/'.$p);
                if (! is_file($full)) {
                    return null;
                }
                $mime = @mime_content_type($full) ?: 'image/jpeg';

                return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($full));
            })->filter()->values();

            return ['report' => $r, 'photos' => $photos];
        });

        $pdf = Pdf::loadView('exports.expo', ['items' => $items])->setPaper('a4', 'portrait');

        return $pdf->download('laporan-ekspo.pdf');
    }

    private function expoQuery(Request $request)
    {
        $user = $request->user();
        $query = ExpoReport::with(['school', 'trainer'])->orderByDesc('date');

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }
        // Management melihat semua; selain itu hanya miliknya.
        if (! $user->can('manage expo reports')) {
            $query->where('trainer_id', $user->id);
        }

        return $query->get();
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
