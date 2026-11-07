<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportService
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Requête des ventes filtrée (période, client, marque, catégorie, type…).
     */
    public function salesQuery(array $filters): Builder
    {
        $status = $filters['status'] ?? null;

        return Sale::query()
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['client_id'] ?? null, fn (Builder $q, $v) => $q->where('client_id', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['brand_id'] ?? null, fn (Builder $q, $v) => $q
                ->whereHas('items.product', fn (Builder $p) => $p->where('brand_id', $v)))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q
                ->whereHas('items.product', fn (Builder $p) => $p->where('category_id', $v)))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($status === null, fn (Builder $q) => $q->where('status', '!=', 'cancelled'));
    }

    /**
     * Requête des encaissements filtrée.
     */
    public function paymentsQuery(array $filters): Builder
    {
        return Payment::query()
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->when($filters['client_id'] ?? null, fn (Builder $q, $v) => $q->where('client_id', $v))
            ->when($filters['method'] ?? null, fn (Builder $q, $v) => $q->where('method', $v));
    }

    /**
     * Requête des lignes de vente (produits) filtrée.
     */
    public function itemsQuery(array $filters): Builder
    {
        $status = $filters['status'] ?? null;

        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereNull('sales.deleted_at')
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('sales.date', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('sales.date', '<=', $v))
            ->when($filters['client_id'] ?? null, fn (Builder $q, $v) => $q->where('sales.client_id', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('sales.type', $v))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('sales.user_id', $v))
            ->when($filters['brand_id'] ?? null, fn (Builder $q, $v) => $q->where('products.brand_id', $v))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('products.category_id', $v))
            ->when($status !== null, fn (Builder $q) => $q->where('sales.status', $status))
            ->when($status === null, fn (Builder $q) => $q->where('sales.status', '!=', 'cancelled'));
    }

    /**
     * Statistiques complètes d'une période.
     */
    public function summary(array $filters): array
    {
        $from = Carbon::parse($filters['from']);
        $to = Carbon::parse($filters['to']);
        $days = max(1, (int) $from->diffInDays($to) + 1);

        $base = $this->salesQuery($filters);

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(discount),0) as discount, COALESCE(SUM(total),0) as total, COALESCE(SUM(paid_amount),0) as paid')
            ->first();

        $byType = (clone $base)
            ->selectRaw('type, COALESCE(SUM(total),0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(total),0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = (int) $totals->cnt;
        $salesTotal = round((float) $totals->total, 2);
        $cashTotal = round((float) ($byType['cash'] ?? 0), 2);
        $creditTotal = round((float) ($byType['credit'] ?? 0), 2);

        $statuses = [];
        foreach (['paid', 'partial', 'unpaid', 'cancelled'] as $status) {
            $row = $byStatus->get($status);
            $statuses[$status] = [
                'count' => (int) ($row->cnt ?? 0),
                'total' => round((float) ($row->total ?? 0), 2),
            ];
        }

        $payBase = $this->paymentsQuery($filters);
        $payTotals = (clone $payBase)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount),0) as total')
            ->first();
        $payCount = (int) $payTotals->cnt;
        $payTotal = round((float) $payTotals->total, 2);

        $rawMethods = (clone $payBase)
            ->selectRaw('method, COALESCE(SUM(amount),0) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $byMethod = [];
        foreach (['cash', 'bank', 'cheque', 'card', 'other'] as $method) {
            $byMethod[$method] = round((float) ($rawMethods[$method] ?? 0), 2);
        }

        $marginRow = $this->itemsQuery($filters)
            ->selectRaw('COALESCE(SUM(sale_items.total),0) as revenue, COALESCE(SUM(sale_items.quantity * COALESCE(products.purchase_price,0)),0) as cost')
            ->first();

        $revenue = round((float) $marginRow->revenue, 2);
        $cost = round((float) $marginRow->cost, 2);
        $margin = round($revenue - $cost, 2);

        $topProducts = $this->itemsQuery($filters)
            ->selectRaw('products.id, products.sku, products.name_fr, SUM(sale_items.quantity) as qty, SUM(sale_items.total) as total, SUM(sale_items.quantity * COALESCE(products.purchase_price,0)) as cost')
            ->groupBy('products.id', 'products.sku', 'products.name_fr')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'name' => $row->name_fr,
                'qty' => round((float) $row->qty, 2),
                'total' => round((float) $row->total, 2),
                'margin' => round((float) $row->total - (float) $row->cost, 2),
            ])
            ->all();

        $topClientsRows = $this->salesQuery($filters)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, COUNT(*) as cnt, COALESCE(SUM(total),0) as total')
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $clients = Client::query()
            ->whereIn('id', $topClientsRows->pluck('client_id'))
            ->get(['id', 'code', 'name', 'balance'])
            ->keyBy('id');

        $topClients = $topClientsRows->map(function ($row) use ($clients) {
            $client = $clients->get($row->client_id);

            return [
                'id' => (int) $row->client_id,
                'code' => $client?->code,
                'name' => $client?->name ?? '—',
                'count' => (int) $row->cnt,
                'total' => round((float) $row->total, 2),
                'balance' => round((float) ($client?->balance ?? 0), 2),
            ];
        })->all();

        $byBrand = $this->itemsQuery($filters)
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->selectRaw('brands.name as name, SUM(sale_items.quantity) as qty, SUM(sale_items.total) as total')
            ->groupBy('brands.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'qty' => round((float) $row->qty, 2),
                'total' => round((float) $row->total, 2),
            ])
            ->all();

        $byCategory = $this->itemsQuery($filters)
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.name as name, SUM(sale_items.quantity) as qty, SUM(sale_items.total) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'qty' => round((float) $row->qty, 2),
                'total' => round((float) $row->total, 2),
            ])
            ->all();

        $byUser = $this->salesQuery($filters)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('users.name as name, COUNT(*) as cnt, COALESCE(SUM(sales.total),0) as total')
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->cnt,
                'total' => round((float) $row->total, 2),
            ])
            ->all();

        $series = $this->series($filters, $days);

        $receivables = round((float) Credit::whereIn('status', ['active', 'overdue'])->sum('balance'), 2);

        $overdue = round((float) Installment::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->selectRaw('COALESCE(SUM(amount - paid_amount),0) as s')
            ->value('s'), 2);

        $products = Product::active()->get(['id', 'sku', 'name_fr', 'stock_qty', 'stock_min', 'purchase_price', 'sale_price']);

        $stockValuePurchase = round($products->sum(fn (Product $p) => (float) $p->stock_qty * (float) $p->purchase_price), 2);
        $stockValueSale = round($products->sum(fn (Product $p) => (float) $p->stock_qty * (float) $p->sale_price), 2);

        $lowStock = $products
            ->filter(fn (Product $p) => (float) $p->stock_min > 0 && (float) $p->stock_qty > 0 && (float) $p->stock_qty <= (float) $p->stock_min)
            ->take(10)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name_fr,
                'stock_qty' => round((float) $p->stock_qty, 2),
                'stock_min' => round((float) $p->stock_min, 2),
            ])
            ->values()
            ->all();

        $bestDay = ['label' => '—', 'total' => 0.0];
        foreach ($series['data'] as $point) {
            if ($point['sales'] > $bestDay['total']) {
                $bestDay = ['label' => $point['label'], 'total' => $point['sales']];
            }
        }

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
            ],
            'sales' => [
                'count' => $count,
                'subtotal' => round((float) $totals->subtotal, 2),
                'discount' => round((float) $totals->discount, 2),
                'total' => $salesTotal,
                'cash' => $cashTotal,
                'credit' => $creditTotal,
                'avg_basket' => $count > 0 ? round($salesTotal / $count, 2) : 0.0,
                'paid_at_sale' => round((float) $totals->paid, 2),
                'remaining' => round(max(0, $salesTotal - (float) $totals->paid), 2),
                'daily_average' => round($salesTotal / $days, 2),
                'by_status' => $statuses,
                'best_day' => $bestDay,
            ],
            'payments' => [
                'count' => $payCount,
                'total' => $payTotal,
                'avg' => $payCount > 0 ? round($payTotal / $payCount, 2) : 0.0,
                'by_method' => $byMethod,
            ],
            'margin' => [
                'revenue' => $revenue,
                'cost' => $cost,
                'margin' => $margin,
                'rate' => $revenue > 0 ? round(($margin / $revenue) * 100, 1) : 0.0,
            ],
            'collection_rate' => $creditTotal > 0 ? round(min(100, ($payTotal / $creditTotal) * 100), 1) : 0.0,
            'receivables' => [
                'balance' => $receivables,
                'overdue' => $overdue,
                'clients' => Client::where('balance', '>', 0)->count(),
                'aging' => $this->dashboard->aging(),
            ],
            'clients' => [
                'active' => Client::active()->count(),
                'new' => Client::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'with_sales' => (clone $base)->whereNotNull('client_id')->distinct()->count('client_id'),
            ],
            'stock' => [
                'products' => $products->count(),
                'value_purchase' => $stockValuePurchase,
                'value_sale' => $stockValueSale,
                'low' => $products->filter(fn (Product $p) => (float) $p->stock_min > 0 && (float) $p->stock_qty > 0 && (float) $p->stock_qty <= (float) $p->stock_min)->count(),
                'out' => $products->filter(fn (Product $p) => (float) $p->stock_qty <= 0)->count(),
                'low_list' => $lowStock,
            ],
            'series' => $series['data'],
            'series_mode' => $series['mode'],
            'top_products' => $topProducts,
            'top_clients' => $topClients,
            'by_brand' => $byBrand,
            'by_category' => $byCategory,
            'by_user' => $byUser,
        ];
    }

    /**
     * Série CA / encaissements par jour (ou par mois si période longue).
     *
     * @return array{mode:string, data:array<int, array{label:string, sales:float, payments:float}>}
     */
    private function series(array $filters, int $days): array
    {
        $mode = $days <= 62 ? 'day' : 'month';

        $salesRows = $this->salesQuery($filters)->get(['date', 'total']);
        $paymentRows = $this->paymentsQuery($filters)->get(['paid_at', 'amount']);

        if ($mode === 'day') {
            $buckets = [];
            $cursor = Carbon::parse($filters['from']);
            $end = Carbon::parse($filters['to']);

            while ($cursor <= $end) {
                $buckets[$cursor->toDateString()] = ['sales' => 0.0, 'payments' => 0.0];
                $cursor = $cursor->copy()->addDay();
            }

            foreach ($salesRows as $row) {
                $key = $row->date?->toDateString();
                if ($key !== null && isset($buckets[$key])) {
                    $buckets[$key]['sales'] += (float) $row->total;
                }
            }

            foreach ($paymentRows as $row) {
                $key = $row->paid_at?->toDateString();
                if ($key !== null && isset($buckets[$key])) {
                    $buckets[$key]['payments'] += (float) $row->amount;
                }
            }

            $data = [];
            foreach ($buckets as $key => $values) {
                $data[] = [
                    'label' => Carbon::parse($key)->format('d/m'),
                    'sales' => round($values['sales'], 2),
                    'payments' => round($values['payments'], 2),
                ];
            }

            return ['mode' => $mode, 'data' => $data];
        }

        $buckets = [];
        $cursor = Carbon::parse($filters['from'])->startOfMonth();
        $end = Carbon::parse($filters['to'])->startOfMonth();

        while ($cursor <= $end) {
            $buckets[$cursor->format('Y-m')] = ['sales' => 0.0, 'payments' => 0.0];
            $cursor = $cursor->copy()->addMonth();
        }

        foreach ($salesRows as $row) {
            $key = $row->date?->format('Y-m');
            if ($key !== null && isset($buckets[$key])) {
                $buckets[$key]['sales'] += (float) $row->total;
            }
        }

        foreach ($paymentRows as $row) {
            $key = $row->paid_at?->format('Y-m');
            if ($key !== null && isset($buckets[$key])) {
                $buckets[$key]['payments'] += (float) $row->amount;
            }
        }

        $data = [];
        foreach ($buckets as $key => $values) {
            $data[] = [
                'label' => Carbon::parse($key.'-01')->translatedFormat('M Y'),
                'sales' => round($values['sales'], 2),
                'payments' => round($values['payments'], 2),
            ];
        }

        return ['mode' => $mode, 'data' => $data];
    }
}
