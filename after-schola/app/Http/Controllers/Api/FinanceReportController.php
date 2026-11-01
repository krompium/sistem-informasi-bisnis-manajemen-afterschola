<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeReportRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Support\IncomeReport;
use Illuminate\Http\Request;

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
        $invoices = Invoice::visibleTo($request->user())->with('registration.program')
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

    /**
     * Laporan pemasukan per periode, cabang, dan program (KEU-07). Export: IncomeExportController.
     */
    public function income(IncomeReportRequest $request)
    {
        $report = IncomeReport::fromRequest($request);

        return response()->json(['data' => $report->summary() + ['options' => $report->options()]]);
    }
}
