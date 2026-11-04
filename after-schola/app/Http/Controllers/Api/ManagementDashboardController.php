<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeReportRequest;
use App\Support\DashboardMetrics;

/** Dashboard Management jalur reguler (MGT-60 sampai 62). Permission "view dashboard" dijaga middleware route; angka dibatasi scope cabang. */
class ManagementDashboardController extends Controller
{
    public function kpi(IncomeReportRequest $request)
    {
        return response()->json(['data' => DashboardMetrics::fromRequest($request)->kpi()]);
    }

    public function funnel(IncomeReportRequest $request)
    {
        return response()->json(['data' => DashboardMetrics::fromRequest($request)->funnel()]);
    }

    public function panel(IncomeReportRequest $request)
    {
        return response()->json(['data' => DashboardMetrics::fromRequest($request)->panel()]);
    }
}
