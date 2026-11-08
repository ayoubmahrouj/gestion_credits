<?php
declare(strict_types=1);

require_login();

/* -------------------------------------------------------------------------
| Filtres
|-------------------------------------------------------------------------*/

$q = trim((string) get('q'));
$from = (string) get('from', date('Y-m-01'));
$to = (string) get('to', today());
$status = (string) get('status');
$method = (string) get('method');
$clientId = (int) get('client_id');

$sql = 'SELECT s.*, c.name AS client_name, c.phone AS client_phone
        FROM sales s JOIN clients c ON c.id = s.client_id
        WHERE s.date BETWEEN ? AND ?';
$params = [$from, $to];

if ($q !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR s.id = ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = (int) $q;
}

if (in_array($status, ['paid', 'credit', 'partial'], true)) {
    $sql .= ' AND s.status = ?';
    $params[] = $status;
}

if (in_array($method, ['cash', 'card', 'transfer', 'credit'], true)) {
    $sql .= ' AND s.payment_method = ?';
    $params[] = $method;
}

if ($clientId > 0) {
    $sql .= ' AND s.client_id = ?';
    $params[] = $clientId;
}

$sql .= ' ORDER BY s.date DESC, s.id DESC';
$sales = all($sql, $params);

$sumTotal = 0.0;
$sumPaid = 0.0;
foreach ($sales as $sale) {
    $sumTotal += (float) $sale['total'];
    $sumPaid += (float) $sale['paid'];
}

$clients = all('SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name COLLATE NOCASE');

layout_start(t('sales'), 'sales');
print_header(t('sales'), fr_date($from) . ' → ' . fr_date($to));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="sales">
        <label class="field">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('client') . ' / ' . t('phone')) ?>">
        </label>
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
        <button class="btn btn-ghost" type="submit"><?= e(t('filter')) ?></button>
        <a class="btn btn-ghost" href="<?= url('sales') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('export', ['type' => 'sales', 'from' => $from, 'to' => $to]) ?>"><?= e(t('export_excel')) ?></a>
    <?php print_button(); ?>
    <a class="btn btn-primary" href="<?= url('sale_new') ?>">+ <?= e(t('sale_new')) ?></a>
</div>

<div class="grid grid-3">
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_sales')) ?></div>
        <div class="stat-value"><?= money($sumTotal) ?></div>
        <div class="stat-foot"><?= count($sales) ?> <?= e(t('sales')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_paid')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($sumPaid) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_credit')) ?></div>
        <div class="stat-value" style="color:var(--orange)"><?= money($sumTotal - $sumPaid) ?></div>
    </div>
</div>

<div class="card">
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
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $sales): ?>
                <tr><td colspan="9" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td class="muted">#<?= (int) $sale['id'] ?></td>
                    <td><?= fr_date($sale['date']) ?></td>
                    <td>
                        <a href="<?= url('client', ['id' => $sale['client_id']]) ?>" style="font-weight:600"><?= e($sale['client_name']) ?></a>
                        <div class="small muted"><?= e($sale['client_phone'] ?: '') ?></div>
                    </td>
                    <td class="num"><?= money($sale['total']) ?></td>
                    <td class="num"><?= money($sale['paid']) ?></td>
                    <td class="num" style="font-weight:700"><?= money((float) $sale['total'] - (float) $sale['paid']) ?></td>
                    <td><?= e(t($sale['payment_method'])) ?></td>
                    <td>
                        <span class="badge <?= $sale['status'] === 'paid' ? 'badge-green' : ($sale['status'] === 'partial' ? 'badge-orange' : 'badge-red') ?>">
                            <?= e(sale_status_label($sale['status'])) ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('sale', ['id' => $sale['id']]) ?>"><?= e(t('sale_detail')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= url('ticket', ['type' => 'sale', 'id' => $sale['id']]) ?>" target="_blank"><?= e(t('ticket')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php layout_end(); ?>
