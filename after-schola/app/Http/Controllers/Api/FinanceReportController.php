<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Support\IncomeReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Laporan keuangan. Hak akses: permission "view finance reports" (dipasang di routes).
 */
class FinanceReportController extends Controller
{
    /**
     * Umur piutang: invoice unpaid dikelompokkan menurut hari keterlambatan dari jatuh tempo.
     */
    public function aging(Request $request)
    {
        $invoices = Invoice::inBranchesOf($request->user())->with('registration.program')
            ->where('status', 'unpaid')->orderBy('due_date')->get();

        $empty = fn () => ['count' => 0, 'total' => 0.0];
        $buckets = [
            'belum_jatuh_tempo' => $empty(), 'd1_30' => $empty(), 'd31_60' => $empty(),
            'd61_90' => $empty(), 'd90_plus' => $empty(),
        ];
        $total = $empty();

        foreach ($invoices as $invoice) {
            $days = $invoice->daysOverdue();
            $key = match (true) {
                $days === 0 => 'belum_jatuh_tempo',
                $days <= 30 => 'd1_30',
                $days <= 60 => 'd31_60',
                $days <= 90 => 'd61_90',
                default => 'd90_plus',
            };
            $buckets[$key]['count']++;
            $buckets[$key]['total'] += (float) $invoice->amount;
            $total['count']++;
            $total['total'] += (float) $invoice->amount;
        }

        return response()->json(['data' => [
            'buckets' => $buckets,
            'total' => $total,
            'invoices' => InvoiceResource::collection($invoices)->resolve(),
        ]]);
    }

    /** Pemasukan per periode (KEU-07): total, per program, dan per cabang. */
    public function income(Request $request)
    {
        return response()->json(['data' => $this->report($request)->summary()]);
    }

    public function incomeExcel(Request $request)
    {
        $report = $this->report($request);
        $zone = config('app.display_timezone');
        $total = 0.0;

        return response()->streamDownload(function () use ($report, $zone, &$total) {
            $writer = new Writer;
            $writer->getOptions()->setColumnWidthForRange(22, 1, 8);
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(
                ['Tanggal Konfirmasi', 'Nomor Invoice', 'Siswa', 'Orang Tua', 'Program', 'Cabang', 'Metode', 'Nominal'],
                (new Style)->withFontBold(true)->withBackgroundColor('92D050')
            ));

            // Dibaca per potong supaya export ratusan baris tidak menghabiskan memori (NFR-02).
            $report->query()->chunkById(200, function ($payments) use ($writer, $zone, &$total) {
                foreach ($payments as $payment) {
                    $registration = $payment->invoice->registration;
                    $total += (float) $payment->amount;
                    $writer->addRow(Row::fromValues([
                        $payment->confirmed_at->copy()->timezone($zone)->format('d/m/Y H:i'),
                        $payment->invoice->number,
                        $registration->student_name,
                        $registration->parent_name,
                        $registration->program?->name ?? '',
                        $registration->branch?->name ?? '',
                        ucfirst($payment->method),
                        (float) $payment->amount,
                    ]));
                }
            });

            $writer->addRow(Row::fromValuesWithStyle(['', '', '', '', '', '', 'Total', $total], (new Style)->withFontBold(true)));
            $writer->close();
        }, $report->filenameBase().'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** TODO(nanti): PDF memuat semua baris di memori; bila periode ratusan baris terasa berat, batasi atau chunk. */
    public function incomePdf(Request $request)
    {
        $report = $this->report($request);

        return Pdf::loadView('exports.income', ['report' => $report, 'summary' => $report->summary(), 'payments' => $report->payments()])
            ->setPaper('a4', 'portrait')
            ->download($report->filenameBase().'.pdf');
    }

    private function report(Request $request): IncomeReport
    {
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
        ]);

        return new IncomeReport($request->user(), $filters);
    }
}
