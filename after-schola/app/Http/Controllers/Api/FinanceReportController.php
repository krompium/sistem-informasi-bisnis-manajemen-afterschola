<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Requests\IncomeReportRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Laporan keuangan. Hak akses: permission "view finance reports" (dipasang di routes).
 * Semua data dibatasi cabang user (BR-19).
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

    /**
     * KEU-07 / KPI income: payment confirmed dengan confirmed_at dalam periode, per cabang dan per program.
     * TODO(nanti): bila periode sangat panjang, hitung lewat query agregat, bukan di PHP.
     */
    public function income(IncomeReportRequest $request)
    {
        $payments = Payment::income($request->user(), $request->validated())
            ->with('invoice.registration.branch:id,name', 'invoice.registration.program:id,name')->get();

        $group = fn (string $relation) => $payments
            ->groupBy(fn ($p) => $p->invoice->registration->{$relation}?->id ?? 0)
            ->map(fn ($rows) => [
                'id' => $rows->first()->invoice->registration->{$relation}?->id,
                'name' => $rows->first()->invoice->registration->{$relation}?->name ?? '-',
                'total' => (float) $rows->sum('amount'),
                'count' => $rows->count(),
            ])->sortByDesc('total')->values();

        return response()->json(['data' => [
            'from' => $request->validated('from'),
            'to' => $request->validated('to'),
            'total' => (float) $payments->sum('amount'),
            'count' => $payments->count(),
            'by_branch' => $group('branch'),
            'by_program' => $group('program'),
        ]]);
    }
}
