<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function kpis(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $salesToday = (float) Sale::whereDate('date', $today)->where('status', '!=', 'cancelled')->sum('total');
        $salesMonth = (float) Sale::whereDate('date', '>=', $monthStart)->where('status', '!=', 'cancelled')->sum('total');
        $sales90 = (float) Sale::whereDate('date', '>=', now()->subDays(90)->toDateString())->where('status', '!=', 'cancelled')->sum('total');

        $receivables = (float) Credit::whereIn('status', ['active', 'overdue'])->sum('balance');

        $overdueAmount = (float) Installment::whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_date', '<', $today)
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as s')
            ->value('s');

        $paymentsToday = (float) Payment::whereDate('paid_at', $today)->sum('amount');

        $dso = $sales90 > 0 ? round(($receivables / $sales90) * 90, 1) : 0;

        return [
            'sales_today' => round($salesToday, 2),
            'sales_month' => round($salesMonth, 2),
            'receivables' => round($receivables, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'payments_today' => round($paymentsToday, 2),
            'dso' => $dso,
            'clients_total' => Client::count(),
            'clients_overdue' => Client::whereHas('credits.installments', fn ($q) => $q
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->whereDate('due_date', '<', $today))->count(),
            'low_stock' => Product::active()->lowStock()->count(),
            'avg_score' => round((float) Client::avg('score'), 0),
        ];
    }

    /**
     * Balance âgée des créances (aging).
     */
    public function aging(): array
    {
        $today = now()->startOfDay();
        $rows = Installment::whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('credit', fn ($q) => $q->whereIn('status', ['active', 'overdue']))
            ->get(['due_date', 'amount', 'paid_amount']);

        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];

        foreach ($rows as $row) {
            $remaining = max(0, (float) $row->amount - (float) $row->paid_amount);
            $days = $row->due_date->diffInDays($today, false);

            $key = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => '1_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$key] += $remaining;
        }

        return array_map(fn ($v) => round($v, 2), $buckets);
    }

    public function topDebtors(int $limit = 5): array
    {
        return Client::query()
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit($limit)
            ->get(['id', 'code', 'name', 'phone', 'balance', 'score', 'status'])
            ->toArray();
    }

    public function monthlySeries(int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $sales = Sale::where('date', '>=', $start->toDateString())
            ->where('status', '!=', 'cancelled')
            ->get(['date', 'total'])
            ->groupBy(fn (Sale $sale) => $sale->date->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('total'));

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $series[] = [
                'month' => $m->format('Y-m'),
                'label' => $m->translatedFormat('M Y'),
                'total' => round((float) ($sales[$m->format('Y-m')] ?? 0), 2),
            ];
        }

        return $series;
    }

    public function recentActivity(int $limit = 8): array
    {
        return Payment::with('client:id,name')
            ->latest('paid_at')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'client' => $p->client?->name,
            ])
            ->toArray();
    }
}
