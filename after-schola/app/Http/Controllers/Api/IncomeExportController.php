<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeReportRequest;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * KEU-07: export laporan pemasukan (Excel dan PDF). Periode dan scope cabang sama dengan GET /finance/income.
 */
class IncomeExportController extends Controller
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const HEADER = ['Tanggal Konfirmasi', 'No. Invoice', 'Siswa', 'Program', 'Cabang', 'Metode', 'Jumlah'];

    private const WITH = ['invoice.registration.branch:id,name', 'invoice.registration.program:id,name'];

    public function excel(IncomeReportRequest $request)
    {
        $filters = $request->validated();
        $query = Payment::income($request->user(), $filters)->with(self::WITH);

        return response()->streamDownload(function () use ($query) {
            $writer = new Writer;
            $writer->getOptions()->setColumnWidthForRange(22, 1, 7);
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(self::HEADER, (new Style)->withFontBold(true)->withBackgroundColor('92D050')));

            $total = 0.0;
            // Dibaca per potong supaya export ratusan baris tidak menghabiskan memori (NFR-02).
            $query->chunkById(200, function ($payments) use ($writer, &$total) {
                foreach ($payments as $p) {
                    $writer->addRow(Row::fromValues($this->line($p)));
                    $total += (float) $p->amount;
                }
            }, 'payments.id', 'id');

            $writer->addRow(Row::fromValuesWithStyle(['', '', '', '', '', 'TOTAL', $total], (new Style)->withFontBold(true)));
            $writer->close();
        }, $this->filename($filters, 'xlsx'), ['Content-Type' => self::XLSX]);
    }

    public function pdf(IncomeReportRequest $request)
    {
        $filters = $request->validated();
        $payments = Payment::income($request->user(), $filters)->with(self::WITH)->orderBy('confirmed_at')->get();

        return Pdf::loadView('exports.income', [
            'filters' => $filters,
            'rows' => $payments->map(fn ($p) => $this->line($p)),
            'total' => (float) $payments->sum('amount'),
        ])->setPaper('a4', 'landscape')->download($this->filename($filters, 'pdf'));
    }

    private function line(Payment $p): array
    {
        $registration = $p->invoice->registration;

        return [
            $p->confirmed_at->copy()->timezone(config('app.display_timezone'))->format('d/m/Y H:i'),
            $p->invoice->number,
            $registration->student_name,
            $registration->program?->name ?? '-',
            $registration->branch?->name ?? '-',
            $p->method === 'transfer' ? 'Transfer' : 'Tunai',
            (float) $p->amount,
        ];
    }

    private function filename(array $filters, string $ext): string
    {
        return "pemasukan-{$filters['from']}-{$filters['to']}.{$ext}";
    }
}
