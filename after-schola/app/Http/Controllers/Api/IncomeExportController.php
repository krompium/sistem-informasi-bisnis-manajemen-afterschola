<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeReportRequest;
use App\Support\IncomeReport;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

// KEU-07: export laporan pemasukan. Filter dan scope cabang sama dengan GET /finance/income.
// Hak akses: permission "view finance reports" (dipasang di routes).
class IncomeExportController extends Controller
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const HEADER = ['Tanggal Konfirmasi', 'Nomor Invoice', 'Nama Siswa', 'Nama Orang Tua', 'Program', 'Cabang', 'Metode', 'Jumlah (Rp)'];

    public function excel(IncomeReportRequest $request)
    {
        $report = IncomeReport::fromRequest($request);

        return response()->streamDownload(function () use ($report) {
            $writer = new Writer;
            $writer->getOptions()->setColumnWidthForRange(20, 1, 8);
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(self::HEADER, (new Style)->withFontBold(true)->withBackgroundColor('92D050')));

            // Dibaca per potong supaya export ratusan baris tidak menghabiskan memori (NFR-02).
            $total = 0.0;
            $report->query()->chunkById(200, function ($payments) use ($writer, $report, &$total) {
                foreach ($payments as $payment) {
                    $row = $report->row($payment);
                    $total += $row['amount'];
                    $writer->addRow(Row::fromValues([
                        $payment->confirmed_at->copy()->timezone(config('app.display_timezone'))->format('d/m/Y H:i'),
                        $row['invoice_number'],
                        $row['student_name'],
                        $row['parent_name'],
                        $row['program_name'] ?? '',
                        $row['branch_name'] ?? '',
                        $row['method'] === 'tunai' ? 'Tunai' : 'Transfer',
                        $row['amount'],
                    ]));
                }
            });

            $writer->addRow(Row::fromValuesWithStyle(['TOTAL', '', '', '', '', '', '', $total], (new Style)->withFontBold(true)));
            $writer->close();
        }, $report->filenameBase().'.xlsx', ['Content-Type' => self::XLSX]);
    }

    public function pdf(IncomeReportRequest $request)
    {
        $report = IncomeReport::fromRequest($request);

        return Pdf::loadView('exports.income', ['summary' => $report->summary(), 'from' => $report->from, 'to' => $report->to])
            ->setPaper('a4', 'portrait')
            ->download($report->filenameBase().'.pdf');
    }
}
