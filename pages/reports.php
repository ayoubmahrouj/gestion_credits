<?php
declare(strict_types=1);

require_login();

/* -------------------------------------------------------------------------
| Critères
|-------------------------------------------------------------------------*/

$from = (string) get('from', date('Y-m-01'));
$to = (string) get('to', today());
$clientId = (int) get('client_id');
$status = (string) get('status');
$method = (string) get('method');

$where = 's.date BETWEEN ? AND ?';
$params = [$from, $to];

if ($clientId > 0) {
    $where .= ' AND s.client_id = ?';
    $params[] = $clientId;
}

if (in_array($status, ['paid', 'partial', 'credit'], true)) {
    $where .= ' AND s.status = ?';
    $params[] = $status;
}

if (in_array($method, ['cash', 'card', 'transfer', 'credit'], true)) {
    $where .= ' AND s.payment_method = ?';
    $params[] = $method;
}

/* -------------------------------------------------------------------------
| Ventes de la période
|-------------------------------------------------------------------------*/

$sales = all(
    "SELECT s.*, c.name AS client_name FROM sales s JOIN clients c ON c.id = s.client_id
     WHERE {$where} ORDER BY s.date, s.id",
    $params
);

$totalSales = 0.0;
$totalPaid = 0.0;
$countSales = count($sales);

foreach ($sales as $sale) {
    $totalSales += (float) $sale['total'];
    $totalPaid += (float) $sale['paid'];
}

$avgTicket = $countSales > 0 ? $totalSales / $countSales : 0;

/* -------------------------------------------------------------------------
| Versements
|-------------------------------------------------------------------------*/

$paymentWhere = 'p.date BETWEEN ? AND ?';
$paymentParams = [$from, $to];

if ($clientId > 0) {
    $paymentWhere .= ' AND p.client_id = ?';
    $paymentParams[] = $clientId;
}

if (in_array($method, ['cash', 'card', 'transfer'], true)) {
    $paymentWhere .= ' AND p.method = ?';
    $paymentParams[] = $method;
}

$payments = all(
    "SELECT p.*, c.name AS client_name FROM payments p JOIN clients c ON c.id = p.client_id
     WHERE {$paymentWhere} ORDER BY p.date, p.id",
    $paymentParams
);

$totalPayments = 0.0;
foreach ($payments as $payment) {
    $totalPayments += (float) $payment['amount'];
}

/* -------------------------------------------------------------------------
| Meilleurs produits
|-------------------------------------------------------------------------*/

$topProducts = all(
    "SELECT si.brand, si.product_name, SUM(si.qty) AS qty, SUM(si.total) AS revenue
     FROM sale_items si JOIN sales s ON s.id = si.sale_id
     WHERE {$where}
     GROUP BY si.brand, si.product_name ORDER BY revenue DESC LIMIT 12",
    $params
);

/* -------------------------------------------------------------------------
| Meilleurs clients
|-------------------------------------------------------------------------*/

$byClient = [];

foreach ($sales as $sale) {
    $key = (int) $sale['client_id'];

    if (! isset($byClient[$key])) {
        $byClient[$key] = [
            'id' => $key,
            'name' => $sale['client_name'],
            'total' => 0.0,
            'paid' => 0.0,
            'count' => 0,
        ];
    }

    $byClient[$key]['total'] += (float) $sale['total'];
    $byClient[$key]['paid'] += (float) $sale['paid'];
    $byClient[$key]['count']++;
}

usort($byClient, fn ($a, $b) => $b['total'] <=> $a['total']);
$topClients = array_slice($byClient, 0, 12);

foreach ($topClients as &$row) {
    $row['balance'] = client_balance((int) $row['id']);
}
unset($row);

/* -------------------------------------------------------------------------
| Ancienneté des crédits (ouverts)
|-------------------------------------------------------------------------*/

$agingSql = "SELECT cr.*, c.name AS client_name FROM credits cr JOIN clients c ON c.id = cr.client_id
             WHERE cr.status = 'open'";
$agingParams = [];

if ($clientId > 0) {
    $agingSql .= ' AND cr.client_id = ?';
    $agingParams[] = $clientId;
}

$openCredits = all($agingSql, $agingParams);

$aging = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
$overdueTotal = 0.0;
$todayTs = strtotime(today());

foreach ($openCredits as $credit) {
    $days = (int) floor(($todayTs - strtotime($credit['created_at'])) / 86400);

    if ($days <= 30) {
        $aging['0-30'] += (float) $credit['remaining'];
    } elseif ($days <= 60) {
        $aging['31-60'] += (float) $credit['remaining'];
    } elseif ($days <= 90) {
        $aging['61-90'] += (float) $credit['remaining'];
    } else {
        $aging['90+'] += (float) $credit['remaining'];
    }

    if ($credit['due_date'] && $credit['due_date'] < today()) {
        $overdueTotal += (float) $credit['remaining'];
    }
}

$clients = all('SELECT id, name FROM clients WHERE is_active = 1 AND is_passenger = 0 ORDER BY name');
$clientName = '';

foreach ($clients as $client) {
    if ((int) $client['id'] === $clientId) {
        $clientName = $client['name'];
    }
}

layout_start(t('reports'), 'reports');
print_header(t('reports'), fr_date($from) . ' → ' . fr_date($to) . ($clientName !== '' ? ' · ' . $clientName : ''));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="reports">
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
        <label class="field">
            <span><?= e(t('status')) ?></span>
            <select name="status">
                <option value=""><?= e(t('all')) ?></option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>><?= e(t('status_paid')) ?></option>
                <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>><?= e(t('status_partial')) ?></option>
                <option value="credit" <?= $status === 'credit' ? 'selected' : '' ?>><?= e(t('status_credit')) ?></option>
            </select>
        </label>
        <label class="field">
            <span><?= e(t('payment_method')) ?></span>
            <select name="method">
                <option value=""><?= e(t('all')) ?></option>
                <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>><?= e(t('cash')) ?></option>
                <option value="card" <?= $method === 'card' ? 'selected' : '' ?>><?= e(t('card')) ?></option>
                <option value="transfer" <?= $method === 'transfer' ? 'selected' : '' ?>><?= e(t('transfer')) ?></option>
                <option value="credit" <?= $method === 'credit' ? 'selected' : '' ?>><?= e(t('credit')) ?></option>
            </select>
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('apply')) ?></button>
        <a class="btn btn-ghost" href="<?= url('reports') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('export', ['type' => 'report', 'from' => $from, 'to' => $to, 'client_id' => $clientId, 'status' => $status, 'method' => $method]) ?>"><?= e(t('export_excel')) ?></a>
    <?php print_button(); ?>
</div>

<div class="grid grid-4">
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_sales')) ?></div>
        <div class="stat-value"><?= money($totalSales) ?></div>
        <div class="stat-foot"><?= $countSales ?> <?= e(t('sales')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_paid')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($totalPaid) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_credit')) ?></div>
        <div class="stat-value" style="color:var(--orange)"><?= money($totalSales - $totalPaid) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_payments')) ?></div>
        <div class="stat-value"><?= money($totalPayments) ?></div>
        <div class="stat-foot"><?= count($payments) ?> <?= e(t('payments')) ?></div>
    </div>
</div>

<div class="grid grid-3">
    <div class="card stat">
        <div class="stat-label"><?= e(t('period')) ?></div>
        <div class="stat-value" style="font-size:15px"><?= fr_date($from) ?> → <?= fr_date($to) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('total')) ?> / <?= e(t('sales')) ?></div>
        <div class="stat-value"><?= money($avgTicket) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('overdue')) ?></div>
        <div class="stat-value" style="color:var(--red)"><?= money($overdueTotal) ?></div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('top_products')) ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('product')) ?></th>
                    <th class="num"><?= e(t('qty')) ?></th>
                    <th class="num"><?= e(t('total')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (! $topProducts): ?>
                    <tr><td colspan="3" class="empty"><?= e(t('no_results')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td style="font-weight:600"><?= e(item_label($product)) ?></td>
                        <td class="num"><?= rtrim(rtrim(number_format((float) $product['qty'], 2, ',', ' '), '0'), ',') ?></td>
                        <td class="num"><?= money($product['revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('top_clients')) ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('client')) ?></th>
                    <th class="num"><?= e(t('total')) ?></th>
                    <th class="num"><?= e(t('balance')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (! $topClients): ?>
                    <tr><td colspan="3" class="empty"><?= e(t('no_results')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($topClients as $client): ?>
                    <tr>
                        <td><a href="<?= url('client', ['id' => $client['id']]) ?>"><?= e($client['name']) ?></a></td>
                        <td class="num"><?= money($client['total']) ?></td>
                        <td class="num" style="color:<?= $client['balance'] > 0 ? 'var(--orange)' : 'inherit' ?>"><?= money($client['balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('credits_aging')) ?></h2></div>
        <div class="card-body grid" style="gap:14px">
            <?php
            $agingLabels = [
                '0-30' => t('aging_0_30'),
                '31-60' => t('aging_31_60'),
                '61-90' => t('aging_61_90'),
                '90+' => t('aging_90'),
            ];
            $agingMax = max(1, max($aging));
            ?>
            <?php foreach ($aging as $key => $amount): ?>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px">
                        <span class="muted"><?= e($agingLabels[$key]) ?></span>
                        <strong><?= money($amount) ?></strong>
                    </div>
                    <div class="bar"><i style="width:<?= (int) round($amount / $agingMax * 100) ?>%"></i></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('payments')) ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('date')) ?></th>
                    <th><?= e(t('client')) ?></th>
                    <th><?= e(t('method')) ?></th>
                    <th class="num"><?= e(t('amount')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (! $payments): ?>
                    <tr><td colspan="4" class="empty"><?= e(t('no_results')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= fr_date($payment['date']) ?></td>
                        <td><?= e($payment['client_name']) ?></td>
                        <td><?= e(t($payment['method'])) ?></td>
                        <td class="num"><?= money($payment['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2><?= e(t('sales')) ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>#</th>
                <th><?= e(t('date')) ?></th>
                <th><?= e(t('client')) ?></th>
                <th class="num"><?= e(t('total')) ?></th>
                <th class="num"><?= e(t('paid')) ?></th>
                <th class="num"><?= e(t('remaining')) ?></th>
                <th><?= e(t('payment_method')) ?></th>
                <th><?= e(t('status')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $sales): ?>
                <tr><td colspan="8" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td class="muted">#<?= (int) $sale['id'] ?></td>
                    <td><?= fr_date($sale['date']) ?></td>
                    <td><?= e($sale['client_name']) ?></td>
                    <td class="num"><?= money($sale['total']) ?></td>
                    <td class="num"><?= money($sale['paid']) ?></td>
                    <td class="num"><?= money((float) $sale['total'] - (float) $sale['paid']) ?></td>
                    <td><?= e(t($sale['payment_method'])) ?></td>
                    <td><?= e(sale_status_label($sale['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="3"><?= e(t('total')) ?></th>
                <th class="num"><?= money($totalSales) ?></th>
                <th class="num"><?= money($totalPaid) ?></th>
                <th class="num"><?= money($totalSales - $totalPaid) ?></th>
                <th colspan="2"></th>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php layout_end(); ?>
