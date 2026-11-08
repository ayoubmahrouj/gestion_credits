<?php
declare(strict_types=1);

require_login();

$q = trim((string) get('q'));
$status = (string) get('status', 'open');
$overdue = (string) get('overdue');
$from = (string) get('from');
$to = (string) get('to');
$clientId = (int) get('client_id');

$sql = 'SELECT cr.*, c.name AS client_name, c.phone AS client_phone, s.date AS sale_date
        FROM credits cr
        JOIN clients c ON c.id = cr.client_id
        JOIN sales s ON s.id = cr.sale_id
        WHERE 1 = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($status === 'open' || $status === 'paid') {
    $sql .= ' AND cr.status = ?';
    $params[] = $status;
}

if ($overdue === '1') {
    $sql .= " AND cr.status = 'open' AND cr.due_date IS NOT NULL AND cr.due_date < ?";
    $params[] = today();
}

if ($from !== '') {
    $sql .= ' AND date(cr.created_at) >= ?';
    $params[] = $from;
}

if ($to !== '') {
    $sql .= ' AND date(cr.created_at) <= ?';
    $params[] = $to;
}

if ($clientId > 0) {
    $sql .= ' AND cr.client_id = ?';
    $params[] = $clientId;
}

$sql .= ' ORDER BY (cr.status = \'open\') DESC, cr.due_date IS NULL, cr.due_date, cr.id DESC';
$credits = all($sql, $params);

$sumOpen = 0.0;
$sumOverdue = 0.0;
$sumPaid = 0.0;
foreach ($credits as $credit) {
    if ($credit['status'] === 'open') {
        $sumOpen += (float) $credit['remaining'];
        if ($credit['due_date'] && $credit['due_date'] < today()) {
            $sumOverdue += (float) $credit['remaining'];
        }
    } else {
        $sumPaid += (float) $credit['amount'];
    }
}

$clients = all('SELECT id, name FROM clients WHERE is_active = 1 AND is_passenger = 0 ORDER BY name COLLATE NOCASE');

layout_start(t('credits'), 'credits');
print_header(t('credits'), $status === 'open' ? t('status_open') : '');
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="credits">
        <label class="field">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('client') . ' / ' . t('phone')) ?>">
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
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>><?= e(t('status_open')) ?></option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>><?= e(t('status_paid')) ?></option>
            </select>
        </label>
        <label class="field">
            <span><?= e(t('from')) ?></span>
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label class="field">
            <span><?= e(t('to')) ?></span>
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <label class="field" style="min-width:auto">
            <span>&nbsp;</span>
            <label style="display:flex;align-items:center;gap:8px;height:40px">
                <input type="checkbox" name="overdue" value="1" <?= $overdue === '1' ? 'checked' : '' ?> style="width:auto">
                <span class="small"><?= e(t('overdue_only')) ?></span>
            </label>
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('filter')) ?></button>
        <a class="btn btn-ghost" href="<?= url('credits') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('export', ['type' => 'credits', 'status' => $status]) ?>"><?= e(t('export_excel')) ?></a>
    <?php print_button(); ?>
</div>

<div class="grid grid-3">
    <div class="card stat">
        <div class="stat-label"><?= e(t('outstanding')) ?></div>
        <div class="stat-value" style="color:var(--orange)"><?= money($sumOpen) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('overdue')) ?></div>
        <div class="stat-value" style="color:var(--red)"><?= money($sumOverdue) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('status_paid')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($sumPaid) ?></div>
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
                <th class="num"><?= e(t('paid')) ?></th>
                <th class="num"><?= e(t('remaining')) ?></th>
                <th><?= e(t('due_date')) ?></th>
                <th><?= e(t('status')) ?></th>
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $credits): ?>
                <tr><td colspan="9" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($credits as $credit): ?>
                <?php $isOverdue = $credit['status'] === 'open' && $credit['due_date'] && $credit['due_date'] < today(); ?>
                <tr>
                    <td class="muted">#<?= (int) $credit['id'] ?></td>
                    <td><?= fr_date($credit['sale_date']) ?></td>
                    <td>
                        <a href="<?= url('client', ['id' => $credit['client_id']]) ?>" style="font-weight:600"><?= e($credit['client_name']) ?></a>
                        <div class="small muted"><?= e($credit['client_phone'] ?: '') ?></div>
                    </td>
                    <td class="num"><?= money($credit['amount']) ?></td>
                    <td class="num"><?= money($credit['paid']) ?></td>
                    <td class="num" style="font-weight:700"><?= money($credit['remaining']) ?></td>
                    <td>
                        <?= fr_date($credit['due_date']) ?>
                        <?php if ($isOverdue): ?><span class="badge badge-red"><?= e(t('overdue')) ?></span><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $credit['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>">
                            <?= e(credit_status_label($credit['status'])) ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('sale', ['id' => $credit['sale_id']]) ?>"><?= e(t('sale_detail')) ?></a>
                        <?php if ($credit['status'] === 'open'): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= url('payment_new', ['client_id' => $credit['client_id']]) ?>"><?= e(t('payment_new')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php layout_end(); ?>
