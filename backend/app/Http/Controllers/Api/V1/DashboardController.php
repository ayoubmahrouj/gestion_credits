<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index()
    {
        return response()->json([
            'kpis' => $this->dashboard->kpis(),
            'aging' => $this->dashboard->aging(),
            'top_debtors' => $this->dashboard->topDebtors(5),
            'monthly_series' => $this->dashboard->monthlySeries(6),
            'recent_activity' => $this->dashboard->recentActivity(8),
        ]);
    }

    public function aging()
    {
        return response()->json(['data' => $this->dashboard->aging()]);
    }
}
