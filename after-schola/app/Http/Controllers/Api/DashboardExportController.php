<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeReportRequest;
use App\Support\DashboardMetrics;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * MGT-63: export laporan dashboard (periode, KPI, funnel, panel operasional) ke Excel dan PDF.
 * Angka, periode, dan scope cabang persis sama dengan GET /dashboard/kpi, /funnel, /panel (DashboardMetrics).
 * Hak akses: permission "view dashboard" (dipasang di routes). Laporan kecil (puluhan baris), jadi tanpa chunking.
 */
class DashboardExportController extends Controller
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function excel(IncomeReportRequest $request)
    {
        $report = $this->report($request);

        return response()->streamDownload(function () use ($report) {
            $bold = (new Style)->withFontBold(true);
            $header = (new Style)->withFontBold(true)->withBackgroundColor('92D050');
            $writer = new Writer;
            $writer->getOptions()->setColumnWidthForRange(34, 1, 1);
            $writer->getOptions()->setColumnWidthForRange(26, 2, 2);
            $writer->getOptions()->setColumnWidthForRange(40, 3, 3);
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(['Laporan Dashboard Manajemen'], $bold));
            $writer->addRow(Row::fromValues(['Periode', $report['from']->format('d/m/Y').' – '.$report['to']->format('d/m/Y')]));
            $writer->addRow(Row::fromValues(['Cabang', $report['branch']]));
            foreach ($report['sections'] as $title => $rows) {
                $writer->addRow(Row::fromValuesWithStyle([$title, 'Nilai', 'Keterangan'], $header));
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues([$row['label'], $row['value'] ?? '', $row['note']]));
                }
            }
            $writer->close();
        }, $report['filename'].'.xlsx', ['Content-Type' => self::XLSX]);
    }

    public function pdf(IncomeReportRequest $request)
    {
        $report = $this->report($request);

        return Pdf::loadView('exports.dashboard', $report)->setPaper('a4', 'portrait')->download($report['filename'].'.pdf');
    }

    private function report(IncomeReportRequest $request): array
    {
        $metrics = DashboardMetrics::fromRequest($request);
        $kpi = $metrics->kpi();
        $panel = $metrics->panel();
        $from = Carbon::parse($kpi['period']['from']);
        $to = Carbon::parse($kpi['period']['to']);
        $percent = fn (?float $value): string => $value === null ? '–' : $value.'%';

        return [
            'from' => $from,
            'to' => $to,
            'branch' => $kpi['branches']->firstWhere('id', $kpi['branch_id'])?->name ?? 'Semua cabang saya',
            'filename' => 'dashboard-'.$from->format('Ymd').'-'.$to->format('Ymd'),
            // value = angka mentah (Excel), display = teks siap cetak (PDF).
            'sections' => [
                'KPI' => [
                    ['label' => 'Siswa aktif', 'value' => $kpi['students_active']['value'], 'display' => (string) $kpi['students_active']['value'], 'note' => 'per '.$to->format('d/m/Y')],
                    ['label' => 'Income (Rp)', 'value' => $kpi['income']['value'], 'display' => 'Rp '.number_format($kpi['income']['value'], 0, ',', '.'), 'note' => 'pembayaran terkonfirmasi di periode'],
                    ['label' => 'Retensi (%)', 'value' => $kpi['retention']['value'], 'display' => $percent($kpi['retention']['value']), 'note' => "{$kpi['retention']['numerator']} dari {$kpi['retention']['denominator']} siswa yang berakhir"],
                    ['label' => 'Okupansi (%)', 'value' => $kpi['occupancy']['value'], 'display' => $percent($kpi['occupancy']['value']), 'note' => "{$kpi['occupancy']['numerator']} dari {$kpi['occupancy']['denominator']} kursi"],
                ],
                'Funnel pendaftaran' => collect($metrics->funnel()['stages'])->map(fn (array $stage) => [
                    'label' => $stage['label'], 'value' => $stage['count'], 'display' => (string) $stage['count'], 'note' => '',
                ])->all(),
                'Operasional hari ini' => collect([
                    ['Sesi hari ini', 'sessions_today'], ['Siswa hadir hari ini', 'students_present_today'], ['Menunggu verifikasi', 'registrations_waiting'],
                    ['Menunggu konfirmasi pembayaran', 'payments_waiting'], ['Invoice overdue', 'invoices_overdue'],
                ])->map(fn (array $item) => ['label' => $item[0], 'value' => $panel[$item[1]], 'display' => (string) $panel[$item[1]], 'note' => ''])->all(),
            ],
        ];
    }
}
