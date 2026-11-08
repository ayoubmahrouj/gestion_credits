<?php
declare(strict_types=1);

require_login();

$id = (int) get('id');
$client = one('SELECT * FROM clients WHERE id = ?', [$id]);

if (! $client) {
    flash(t('no_results'), 'error');
    redirect('clients');
}

$balance = client_balance($id);

$sales = all(
    'SELECT * FROM sales WHERE client_id = ? ORDER BY date DESC, id DESC',
    [$id]
);

$credits = all(
    'SELECT * FROM credits WHERE client_id = ? ORDER BY created_at DESC, id DESC',
    [$id]
);

$payments = all(
    'SELECT * FROM payments WHERE client_id = ? ORDER BY date DESC, id DESC',
    [$id]
);

$totalSales = (float) scalar('SELECT COALESCE(SUM(total), 0) FROM sales WHERE client_id = ?', [$id]);
$totalPaid = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE client_id = ?', [$id]);

layout_start(t('client_detail'), 'clients');
print_header(t('client_detail'), $client['name']);
?>

<div class="toolbar">
    <a class="btn btn-ghost" href="<?= url('clients') ?>"><?= render_icon('back') ?><?= e(t('back')) ?></a>
    <div class="spacer"></div>
    <?php print_button(); ?>
    <a class="btn btn-ghost" href="<?= url('clients', ['edit' => $client['id']]) ?>"><?= e(t('edit')) ?></a>
    <a class="btn btn-ghost" href="<?= url('sale_new', ['client_id' => $client['id']]) ?>">+ <?= e(t('sale_new')) ?></a>
    <a class="btn btn-primary" href="<?= url('payments', ['new' => 1, 'client_id' => $client['id']]) ?>">+ <?= e(t('payment_new')) ?></a>
</div>

<div class="grid grid-4">
    <div class="card stat">
        <div class="stat-label"><?= e(t('balance')) ?></div>
        <div class="stat-value" style="color:<?= $balance > 0 ? 'var(--orange)' : 'var(--green)' ?>"><?= money($balance) ?></div>
        <div class="stat-foot"><?= e(t('credit_limit')) ?> : <?= money($client['credit_limit']) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('sales')) ?></div>
        <div class="stat-value"><?= money($totalSales) ?></div>
        <div class="stat-foot"><?= count($sales) ?> <?= e(t('sales')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('payments')) ?></div>
        <div class="stat-value"><?= money($totalPaid) ?></div>
        <div class="stat-foot"><?= count($payments) ?> <?= e(t('payments')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('phone')) ?></div>
        <div class="stat-value" style="font-size:18px"><?= e($client['phone'] ?: '—') ?></div>
        <div class="stat-foot"><?= e($client['address'] ?: '—') ?></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('client_credits')) ?></h2>
        <?php if ($balance > 0): ?><span class="badge badge-orange"><?= money($balance) ?></span><?php endif; ?>
    </div>
    <?php if (! $credits): ?>
        <div class="empty"><?= e(t('no_debtors')) ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('date')) ?></th>
                    <th><?= e(t('amount')) ?></th>
                    <th class="num"><?= e(t('paid')) ?></th>
                    <th class="num"><?= e(t('remaining')) ?></th>
                    <th><?= e(t('due_date')) ?></th>
                    <th><?= e(t('status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($credits as $credit): ?>
                    <tr>
                        <td><a href="<?= url('sale', ['id' => $credit['sale_id']]) ?>"><?= fr_date($credit['created_at']) ?></a></td>
                        <td class="num"><?= money($credit['amount']) ?></td>
                        <td class="num"><?= money($credit['paid']) ?></td>
                        <td class="num" style="font-weight:700"><?= money($credit['remaining']) ?></td>
                        <td><?= fr_date($credit['due_date']) ?></td>
                        <td>
                            <?php if ($credit['status'] === 'paid'): ?>
                                <span class="badge badge-green"><?= e(t('status_paid')) ?></span>
                            <?php else: ?>
                                <span class="badge badge-orange"><?= e(t('status_open')) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><h2><?= e(t('client_sales')) ?></h2></div>
    <?php if (! $sales): ?>
        <div class="empty"><?= e(t('no_sales')) ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('date')) ?></th>
                    <th class="num"><?= e(t('total')) ?></th>
                    <th class="num"><?= e(t('paid')) ?></th>
                    <th><?= e(t('payment_method')) ?></th>
                    <th><?= e(t('status')) ?></th>
                    <th class="actions"><?= e(t('actions')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?= fr_date($sale['date']) ?></td>
                        <td class="num"><?= money($sale['total']) ?></td>
                        <td class="num"><?= money($sale['paid']) ?></td>
                        <td><?= e(t($sale['payment_method'])) ?></td>
                        <td><span class="badge <?= $sale['status'] === 'paid' ? 'badge-green' : ($sale['status'] === 'partial' ? 'badge-orange' : 'badge-red') ?>"><?= e(sale_status_label($sale['status'])) ?></span></td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="<?= url('sale', ['id' => $sale['id']]) ?>"><?= e(t('sale_detail')) ?></a>
                            <a class="btn btn-ghost btn-sm" href="<?= url('ticket', ['type' => 'sale', 'id' => $sale['id']]) ?>" target="_blank"><?= e(t('ticket')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><h2><?= e(t('client_payments')) ?></h2></div>
    <?php if (! $payments): ?>
        <div class="empty"><?= e(t('no_payments')) ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('date')) ?></th>
                    <th class="num"><?= e(t('amount')) ?></th>
                    <th><?= e(t('method')) ?></th>
                    <th><?= e(t('notes')) ?></th>
                    <th class="actions"><?= e(t('actions')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= fr_date($payment['date']) ?></td>
                        <td class="num"><?= money($payment['amount']) ?></td>
                        <td><?= e(t($payment['method'])) ?></td>
                        <td class="muted"><?= e($payment['note'] ?: '—') ?></td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="<?= url('ticket', ['type' => 'payment', 'id' => $payment['id']]) ?>" target="_blank"><?= e(t('receipt')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php layout_end(); ?>
