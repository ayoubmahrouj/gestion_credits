<?php
declare(strict_types=1);

require_login();
require __DIR__ . '/../inc/charts.php';

/* -------------------------------------------------------------------------
| Période
|-------------------------------------------------------------------------*/

$from = (string) get('from', date('Y-m-01'));
$to = (string) get('to', today());
$clientId = (int) get('client_id');

if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}

if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = today();
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$fromTs = strtotime($from);
$toTs = strtotime($to);
$days = (int) floor(($toTs - $fromTs) / 86400) + 1;

$prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . max(0, $days - 1) . ' days'));

/* -------------------------------------------------------------------------
| Données de la période
|-------------------------------------------------------------------------*/

$salesWhere = 's.date BETWEEN ? AND ?';
$salesParams = [$from, $to];

$payWhere = 'p.date BETWEEN ? AND ?';
$payParams = [$from, $to];

if ($clientId > 0) {
    $salesWhere .= ' AND s.client_id = ?';
    $salesParams[] = $clientId;
    $payWhere .= ' AND p.client_id = ?';
    $payParams[] = $clientId;
}

$sales = all(
    "SELECT s.*, c.name AS client_name FROM sales s JOIN clients c ON c.id = s.client_id
     WHERE {$salesWhere} ORDER BY s.date, s.id",
    $salesParams
);

$payments = all(
    "SELECT p.*, c.name AS client_name FROM payments p JOIN clients c ON c.id = p.client_id
     WHERE {$payWhere} ORDER BY p.date, p.id",
    $payParams
);

$totalSales = 0.0;
$totalPaidSales = 0.0;

foreach ($sales as $sale) {
    $totalSales += (float) $sale['total'];
    $totalPaidSales += (float) $sale['paid'];
}

$countSales = count($sales);
$avgTicket = $countSales > 0 ? $totalSales / $countSales : 0.0;

$totalPayments = 0.0;
foreach ($payments as $payment) {
    $totalPayments += (float) $payment['amount'];
}

$countPayments = count($payments);
$totalCollected = $totalPaidSales + $totalPayments;
$totalCredit = $totalSales - $totalPaidSales;
$recoveryRate = $totalSales > 0 ? ($totalCollected / $totalSales) * 100 : 0.0;

/* -------------------------------------------------------------------------
| Comparaison avec la période précédente
|-------------------------------------------------------------------------*/

$prevSalesWhere = 's.date BETWEEN ? AND ?';
$prevSalesParams = [$prevFrom, $prevTo];
$prevPayWhere = 'p.date BETWEEN ? AND ?';
$prevPayParams = [$prevFrom, $prevTo];

if ($clientId > 0) {
    $prevSalesWhere .= ' AND s.client_id = ?';
    $prevSalesParams[] = $clientId;
    $prevPayWhere .= ' AND p.client_id = ?';
    $prevPayParams[] = $clientId;
}

$prevTotalSales = (float) scalar("SELECT COALESCE(SUM(s.total), 0) FROM sales s WHERE {$prevSalesWhere}", $prevSalesParams);
$prevCountSales = (int) scalar("SELECT COUNT(*) FROM sales s WHERE {$prevSalesWhere}", $prevSalesParams);
$prevTotalPayments = (float) scalar("SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE {$prevPayWhere}", $prevPayParams);
$prevCollected = (float) scalar(
    "SELECT COALESCE(SUM(s.paid), 0) FROM sales s WHERE {$prevSalesWhere}",
    $prevSalesParams
) + $prevTotalPayments;

$delta = static function (float $current, float $previous): ?float {
    if (abs($previous) < 0.005) {
        return null;
    }

    return (($current - $previous) / abs($previous)) * 100;
};

$deltaHtml = static function (?float $pct): string {
    if ($pct === null) {
        return '<span class="muted small">—</span>';
    }

    $up = $pct >= 0;
    $color = $up ? 'var(--green)' : 'var(--red)';

    return '<span style="color:' . $color . ';font-size:12.5px;font-weight:600">'
        . ($up ? '▲' : '▼') . ' ' . number_format(abs($pct), 1, ',', ' ') . '%</span>';
};

/* -------------------------------------------------------------------------
| Encours et retards
|-------------------------------------------------------------------------*/

$creditSql = "SELECT COALESCE(SUM(remaining), 0) FROM credits WHERE status = 'open'";
$creditParams = [];

if ($clientId > 0) {
    $creditSql .= ' AND client_id = ?';
    $creditParams[] = $clientId;
}

$outstanding = (float) scalar($creditSql, $creditParams);

$overdueSql = "SELECT COALESCE(SUM(remaining), 0) FROM credits
               WHERE status = 'open' AND due_date IS NOT NULL AND due_date < ?";
$overdueParams = [today()];

if ($clientId > 0) {
    $overdueSql .= ' AND client_id = ?';
    $overdueParams[] = $clientId;
}

$overdue = (float) scalar($overdueSql, $overdueParams);

$newClients = (int) scalar(
    'SELECT COUNT(*) FROM clients WHERE date(created_at) BETWEEN ? AND ?',
    [$from, $to]
);

/* -------------------------------------------------------------------------
| Découpage temporel (jour / semaine / mois)
|-------------------------------------------------------------------------*/

$granularity = $days <= 45 ? 'day' : ($days <= 180 ? 'week' : 'month');

$ranges = [];

if ($granularity === 'day') {
    for ($ts = $fromTs; $ts <= $toTs; $ts = strtotime('+1 day', $ts)) {
        $ranges[] = ['label' => date('d/m', $ts), 'start' => date('Y-m-d', $ts), 'end' => date('Y-m-d', $ts)];
    }
} elseif ($granularity === 'week') {
    for ($ts = $fromTs; $ts <= $toTs; $ts = strtotime('+7 days', $ts)) {
        $end = strtotime('+6 days', $ts);
        $end = $end > $toTs ? $toTs : $end;
        $ranges[] = ['label' => date('d/m', $ts), 'start' => date('Y-m-d', $ts), 'end' => date('Y-m-d', $end)];
    }
} else {
    $ts = strtotime(date('Y-m-01', $fromTs));

    while ($ts <= $toTs) {
        $monthStart = date('Y-m-01', $ts);
        $monthEnd = date('Y-m-t', $ts);
        $start = $monthStart < $from ? $from : $monthStart;
        $end = $monthEnd > $to ? $to : $monthEnd;
        $ranges[] = ['label' => date('m/Y', $ts), 'start' => $start, 'end' => $end];
        $ts = strtotime(date('Y-m-01', $ts) . ' +1 month');
    }
}

$buckets = [];
foreach ($ranges as $index => $range) {
    $buckets[$index] = ['count' => 0, 'sales' => 0.0, 'paid' => 0.0, 'payments' => 0.0];
}

$findBucket = static function (string $date) use ($ranges): ?int {
    foreach ($ranges as $index => $range) {
        if ($date >= $range['start'] && $date <= $range['end']) {
            return $index;
        }
    }

    return null;
};

foreach ($sales as $sale) {
    $index = $findBucket((string) $sale['date']);
    if ($index === null) {
        continue;
    }

    $buckets[$index]['count']++;
    $buckets[$index]['sales'] += (float) $sale['total'];
    $buckets[$index]['paid'] += (float) $sale['paid'];
}

foreach ($payments as $payment) {
    $index = $findBucket((string) $payment['date']);
    if ($index === null) {
        continue;
    }

    $buckets[$index]['payments'] += (float) $payment['amount'];
}

$labels = array_column($ranges, 'label');
$seriesSales = array_map(fn ($b) => $b['sales'], array_values($buckets));
$seriesPaid = array_map(fn ($b) => $b['paid'], array_values($buckets));
$seriesPayments = array_map(fn ($b) => $b['payments'], array_values($buckets));

$detail = array_filter(
    $buckets,
    fn (array $bucket): bool => $bucket['count'] > 0 || $bucket['sales'] > 0.009 || $bucket['payments'] > 0.009
);

if (! $detail) {
    $detail = $buckets;
}

/* -------------------------------------------------------------------------
| Répartitions
|-------------------------------------------------------------------------*/

$palette = chart_palette();

$byMethod = [];
foreach ($sales as $sale) {
    $key = (string) $sale['payment_method'];
    $byMethod[$key] = ($byMethod[$key] ?? 0) + (float) $sale['total'];
}

$methodColors = ['cash' => $palette['green'], 'card' => $palette['blue'], 'transfer' => $palette['purple'], 'credit' => $palette['orange']];
$methodItems = [];

foreach (['cash', 'card', 'transfer', 'credit'] as $key) {
    $methodItems[] = [
        'label' => t($key),
        'value' => $byMethod[$key] ?? 0,
        'color' => $methodColors[$key],
    ];
}

$byStatus = [];
foreach ($sales as $sale) {
    $key = (string) $sale['status'];
    $byStatus[$key] = ($byStatus[$key] ?? 0) + (float) $sale['total'];
}

$statusColors = ['paid' => $palette['green'], 'partial' => $palette['orange'], 'credit' => $palette['red']];
$statusItems = [];

foreach (['paid', 'partial', 'credit'] as $key) {
    $statusItems[] = [
        'label' => sale_status_label($key),
        'value' => $byStatus[$key] ?? 0,
        'color' => $statusColors[$key],
    ];
}

/* -------------------------------------------------------------------------
| Top produits / clients
|-------------------------------------------------------------------------*/

$topProducts = all(
    "SELECT si.product_name, SUM(si.qty) AS qty, SUM(si.total) AS revenue
     FROM sale_items si JOIN sales s ON s.id = si.sale_id
     WHERE {$salesWhere}
     GROUP BY si.product_name ORDER BY revenue DESC LIMIT 8",
    $salesParams
);

$byClient = [];
foreach ($sales as $sale) {
    $key = (int) $sale['client_id'];

    if (! isset($byClient[$key])) {
        $byClient[$key] = ['id' => $key, 'name' => $sale['client_name'], 'total' => 0.0, 'count' => 0];
    }

    $byClient[$key]['total'] += (float) $sale['total'];
    $byClient[$key]['count']++;
}

usort($byClient, fn ($a, $b) => $b['total'] <=> $a['total']);
$topClients = array_slice(array_values($byClient), 0, 8);

foreach ($topClients as &$row) {
    $row['balance'] = client_balance((int) $row['id']);
}
unset($row);

/* -------------------------------------------------------------------------
| Ancienneté des crédits
|-------------------------------------------------------------------------*/

$openCreditsSql = "SELECT * FROM credits WHERE status = 'open'";
$openCreditsParams = [];

if ($clientId > 0) {
    $openCreditsSql .= ' AND client_id = ?';
    $openCreditsParams[] = $clientId;
}

$openCredits = all($openCreditsSql, $openCreditsParams);

$aging = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
$todayTs = strtotime(today());

foreach ($openCredits as $credit) {
    $age = (int) floor(($todayTs - strtotime((string) $credit['created_at'])) / 86400);

    if ($age <= 30) {
        $aging['0-30'] += (float) $credit['remaining'];
    } elseif ($age <= 60) {
        $aging['31-60'] += (float) $credit['remaining'];
    } elseif ($age <= 90) {
        $aging['61-90'] += (float) $credit['remaining'];
    } else {
        $aging['90+'] += (float) $credit['remaining'];
    }
}

$agingRows = [
    ['label' => t('aging_0_30'), 'value' => $aging['0-30'], 'color' => $palette['green']],
    ['label' => t('aging_31_60'), 'value' => $aging['31-60'], 'color' => $palette['gold']],
    ['label' => t('aging_61_90'), 'value' => $aging['61-90'], 'color' => $palette['orange']],
    ['label' => t('aging_90'), 'value' => $aging['90+'], 'color' => $palette['red']],
];

/* -------------------------------------------------------------------------
| Clients (filtre)
|-------------------------------------------------------------------------*/

$clients = all('SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name COLLATE NOCASE');
$clientName = '';

foreach ($clients as $client) {
    if ((int) $client['id'] === $clientId) {
        $clientName = (string) $client['name'];
    }
}

/* -------------------------------------------------------------------------
| Affichage
|-------------------------------------------------------------------------*/

$moneyBar = static function (array $rows): void {
    $max = 0.0;
    foreach ($rows as $row) {
        $max = max($max, (float) $row['value']);
    }

    if (! $rows || $max <= 0) {
        echo '<div class="empty">' . e(t('no_data')) . '</div>';
        return;
    }

    echo '<div class="hbar-list">';

    foreach ($rows as $row) {
        $value = max(0.0, (float) $row['value']);
        $width = $max > 0 ? ($value / $max) * 100 : 0;
        $meta = isset($row['meta']) && $row['meta'] !== '' ? ' · ' . $row['meta'] : '';

        echo '<div class="hbar">';
        echo '<div class="hbar-row"><span>' . e($row['label']) . $meta . '</span><strong>' . money($value) . '</strong></div>';
        echo '<div class="bar"><i style="width:' . round($width, 1) . '%;background:' . e($row['color']) . '"></i></div>';
        echo '</div>';
    }

    echo '</div>';
};

$productRows = [];
foreach ($topProducts as $product) {
    $productRows[] = [
        'label' => $product['product_name'],
        'value' => (float) $product['revenue'],
        'meta' => rtrim(rtrim(number_format((float) $product['qty'], 2, ',', ' '), '0'), ',') . ' ' . t('qty'),
        'color' => $palette['gold'],
    ];
}

$clientRows = [];
foreach ($topClients as $row) {
    $clientRows[] = [
        'label' => $row['name'],
        'value' => (float) $row['total'],
        'meta' => $row['count'] . ' ' . t('sales') . ' · ' . t('balance') . ' ' . money($row['balance']),
        'color' => $palette['blue'],
    ];
}

$presets = [
    ['label' => t('preset_today'), 'from' => today(), 'to' => today()],
    ['label' => t('preset_7'), 'from' => date('Y-m-d', strtotime('-6 days')), 'to' => today()],
    ['label' => t('preset_30'), 'from' => date('Y-m-d', strtotime('-29 days')), 'to' => today()],
    ['label' => t('preset_month'), 'from' => date('Y-m-01'), 'to' => today()],
    ['label' => t('preset_year'), 'from' => date('Y-01-01'), 'to' => today()],
];

layout_start(t('stats'), 'stats');
print_header(t('stats'), fr_date($from) . ' → ' . fr_date($to) . ($clientName !== '' ? ' · ' . $clientName : ''));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="stats">
        <label class="field">
            <span><?= e(t('from')) ?></span>
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label class="field">
            <span><?= e(t('to')) ?></span>
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <label class="field">
            <span><?= e(t('client')) ?></span>
            <select name="client_id">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= $clientId === (int) $client['id'] ? 'selected' : '' ?>><?= e($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('apply')) ?></button>
        <a class="btn btn-ghost" href="<?= url('stats') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('export', ['type' => 'stats', 'from' => $from, 'to' => $to, 'client_id' => $clientId]) ?>"><?= e(t('export_excel')) ?></a>
    <?php print_button(); ?>
</div>

<div class="quick-ranges no-print">
    <?php foreach ($presets as $preset): ?>
        <?php $active = $preset['from'] === $from && $preset['to'] === $to; ?>
        <a class="chip <?= $active ? 'is-active' : '' ?>" href="<?= url('stats', ['from' => $preset['from'], 'to' => $preset['to'], 'client_id' => $clientId ?: null]) ?>"><?= e($preset['label']) ?></a>
    <?php endforeach; ?>
</div>

<div class="grid grid-4">
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_sales')) ?></div>
        <div class="stat-value"><?= money($totalSales) ?></div>
        <div class="stat-foot"><?= $deltaHtml($delta($totalSales, $prevTotalSales)) ?> <?= e(t('vs_prev')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('total_collected')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($totalCollected) ?></div>
        <div class="stat-foot"><?= $deltaHtml($delta($totalCollected, $prevCollected)) ?> <?= e(t('vs_prev')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_credit')) ?></div>
        <div class="stat-value" style="color:var(--orange)"><?= money($totalCredit) ?></div>
        <div class="stat-foot"><?= number_format($recoveryRate, 1, ',', ' ') ?>% <?= e(t('recovery_rate')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('total_outstanding')) ?></div>
        <div class="stat-value" style="color:var(--red)"><?= money($outstanding) ?></div>
        <div class="stat-foot"><?= e(t('overdue')) ?> : <?= money($overdue) ?></div>
    </div>
</div>

<div class="grid grid-4">
    <div class="card stat">
        <div class="stat-label"><?= e(t('sales')) ?></div>
        <div class="stat-value"><?= $countSales ?></div>
        <div class="stat-foot"><?= $deltaHtml($delta($countSales, $prevCountSales)) ?> <?= e(t('vs_prev')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('avg_basket')) ?></div>
        <div class="stat-value"><?= money($avgTicket) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_payments')) ?></div>
        <div class="stat-value"><?= money($totalPayments) ?></div>
        <div class="stat-foot"><?= $countPayments ?> <?= e(t('payments')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('new_clients')) ?></div>
        <div class="stat-value"><?= $newClients ?></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('evolution')) ?></h2>
        <span class="muted small"><?= fr_date($from) ?> → <?= fr_date($to) ?></span>
    </div>
    <?= chart_grouped_bars($labels, [
        ['label' => t('kpi_sales'), 'color' => '#d4af37', 'values' => $seriesSales],
        ['label' => t('kpi_paid'), 'color' => '#4ade80', 'values' => $seriesPaid],
        ['label' => t('kpi_payments'), 'color' => '#60a5fa', 'values' => $seriesPayments],
    ], 300) ?>
    <div class="chart-legend">
        <span class="legend-item"><i style="background:#d4af37"></i><?= e(t('kpi_sales')) ?></span>
        <span class="legend-item"><i style="background:#4ade80"></i><?= e(t('kpi_paid')) ?></span>
        <span class="legend-item"><i style="background:#60a5fa"></i><?= e(t('kpi_payments')) ?></span>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('payment_methods')) ?></h2></div>
        <div class="donut-wrap">
            <?= chart_donut($methodItems) ?>
            <div class="chart-legend chart-legend-col">
                <?php foreach ($methodItems as $item): ?>
                    <span class="legend-item">
                        <i style="background:<?= e($item['color']) ?>"></i>
                        <?= e($item['label']) ?>
                        <strong style="margin-inline-start:8px"><?= money((float) $item['value']) ?></strong>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('sales_status')) ?></h2></div>
        <div class="donut-wrap">
            <?= chart_donut($statusItems) ?>
            <div class="chart-legend chart-legend-col">
                <?php foreach ($statusItems as $item): ?>
                    <span class="legend-item">
                        <i style="background:<?= e($item['color']) ?>"></i>
                        <?= e($item['label']) ?>
                        <strong style="margin-inline-start:8px"><?= money((float) $item['value']) ?></strong>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('top_products')) ?></h2></div>
        <?php $moneyBar($productRows); ?>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('top_clients')) ?></h2></div>
        <?php $moneyBar($clientRows); ?>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2><?= e(t('credits_aging')) ?></h2></div>
    <?php $moneyBar($agingRows); ?>
</div>

<div class="card">
    <div class="card-head"><h2><?= e(t('detail_period')) ?></h2></div>
    <div class="table-wrap">
        <table class="table table-compact">
            <thead>
            <tr>
                <th><?= e(t('period')) ?></th>
                <th class="num"><?= e(t('sales')) ?></th>
                <th class="num"><?= e(t('kpi_sales')) ?></th>
                <th class="num"><?= e(t('kpi_paid')) ?></th>
                <th class="num"><?= e(t('kpi_payments')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $detail): ?>
                <tr><td colspan="5" class="empty"><?= e(t('no_data')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($detail as $index => $bucket): ?>
                <tr>
                    <td class="nowrap"><?= e($labels[$index]) ?></td>
                    <td class="num"><?= (int) $bucket['count'] ?></td>
                    <td class="num"><?= money($bucket['sales']) ?></td>
                    <td class="num"><?= money($bucket['paid']) ?></td>
                    <td class="num"><?= money($bucket['payments']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <th><?= e(t('total')) ?></th>
                <th class="num"><?= $countSales ?></th>
                <th class="num"><?= money($totalSales) ?></th>
                <th class="num"><?= money($totalPaidSales) ?></th>
                <th class="num"><?= money($totalPayments) ?></th>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php layout_end(); ?>
