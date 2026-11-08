<?php
declare(strict_types=1);

require_login();

if (get('new') === '1') {
    redirect('payment_new', ['client_id' => (int) get('client_id')]);
}

$q = trim((string) get('q'));
$from = (string) get('from', date('Y-m-01'));
$to = (string) get('to', today());
$method = (string) get('method');
$clientId = (int) get('client_id');

$sql = 'SELECT p.*, c.name AS client_name, c.phone AS client_phone, u.name AS user_name
        FROM payments p
        JOIN clients c ON c.id = p.client_id
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.date BETWEEN ? AND ?';
$params = [$from, $to];

if ($q !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if (in_array($method, ['cash', 'card', 'transfer'], true)) {
    $sql .= ' AND p.method = ?';
    $params[] = $method;
}

if ($clientId > 0) {
    $sql .= ' AND p.client_id = ?';
    $params[] = $clientId;
}

$sql .= ' ORDER BY p.date DESC, p.id DESC';
$payments = all($sql, $params);

$sumAmount = 0.0;
foreach ($payments as $payment) {
    $sumAmount += (float) $payment['amount'];
}

$clients = all('SELECT id, name FROM clients WHERE is_active = 1 AND is_passenger = 0 ORDER BY name COLLATE NOCASE');

layout_start(t('payments'), 'payments');
print_header(t('payments'), fr_date($from) . ' → ' . fr_date($to));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="payments">
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
            <span><?= e(t('method')) ?></span>
            <select name="method">
                <option value=""><?= e(t('all')) ?></option>
                <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>><?= e(t('cash')) ?></option>
                <option value="card" <?= $method === 'card' ? 'selected' : '' ?>><?= e(t('card')) ?></option>
                <option value="transfer" <?= $method === 'transfer' ? 'selected' : '' ?>><?= e(t('transfer')) ?></option>
            </select>
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('filter')) ?></button>
        <a class="btn btn-ghost" href="<?= url('payments') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('export', ['type' => 'payments', 'from' => $from, 'to' => $to]) ?>"><?= e(t('export_excel')) ?></a>
    <?php print_button(); ?>
    <a class="btn btn-primary" href="<?= url('payment_new') ?>">+ <?= e(t('payment_new')) ?></a>
</div>

<div class="grid grid-3">
    <div class="card stat">
        <div class="stat-label"><?= e(t('kpi_payments')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($sumAmount) ?></div>
        <div class="stat-foot"><?= count($payments) ?> <?= e(t('payments')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('period')) ?></div>
        <div class="stat-value" style="font-size:16px"><?= fr_date($from) ?> → <?= fr_date($to) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('clients_count')) ?></div>
        <div class="stat-value"><?= count(array_unique(array_column($payments, 'client_id'))) ?></div>
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
                <th class="num"><?= e(t('amount')) ?></th>
                <th><?= e(t('method')) ?></th>
                <th><?= e(t('notes')) ?></th>
                <th><?= e(t('nav_users')) ?></th>
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $payments): ?>
                <tr><td colspan="8" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td class="muted">#<?= (int) $payment['id'] ?></td>
                    <td><?= fr_date($payment['date']) ?></td>
                    <td>
                        <a href="<?= url('client', ['id' => $payment['client_id']]) ?>" style="font-weight:600"><?= e($payment['client_name']) ?></a>
                        <div class="small muted"><?= e($payment['client_phone'] ?: '') ?></div>
                    </td>
                    <td class="num" style="font-weight:700;color:var(--green)"><?= money($payment['amount']) ?></td>
                    <td><?= e(t($payment['method'])) ?></td>
                    <td class="muted"><?= e($payment['note'] ?: '—') ?></td>
                    <td class="muted"><?= e($payment['user_name'] ?: '—') ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('ticket', ['type' => 'payment', 'id' => $payment['id']]) ?>" target="_blank"><?= e(t('receipt')) ?></a>
                        <?php if (is_admin()): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= url('payment_edit', ['id' => $payment['id']]) ?>"><?= e(t('edit')) ?></a>
                            <form method="post" action="<?= url('payment_delete') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= csrf() ?>">
                                <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit"
                                        data-action="confirm" data-message="<?= e(t('confirm_delete_payment')) ?>">
                                    <?= e(t('delete')) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php layout_end(); ?>
