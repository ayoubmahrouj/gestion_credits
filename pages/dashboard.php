<?php
declare(strict_types=1);

$today = today();
$monthStart = date('Y-m-01');

$salesToday = (float) scalar('SELECT COALESCE(SUM(total), 0) FROM sales WHERE date = ?', [$today]);
$countToday = (int) scalar('SELECT COUNT(*) FROM sales WHERE date = ?', [$today]);
$salesMonth = (float) scalar('SELECT COALESCE(SUM(total), 0) FROM sales WHERE date >= ?', [$monthStart]);
$paymentsToday = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE date = ?', [$today]);
$outstanding = (float) scalar("SELECT COALESCE(SUM(remaining), 0) FROM credits WHERE status = 'open'");
$openCredits = (int) scalar("SELECT COUNT(*) FROM credits WHERE status = 'open'");
$clientsCount = (int) scalar('SELECT COUNT(*) FROM clients WHERE is_active = 1 AND is_passenger = 0');
$productsCount = (int) scalar('SELECT COUNT(*) FROM products WHERE is_active = 1');
$overdueCount = (int) scalar(
    "SELECT COUNT(*) FROM credits WHERE status = 'open' AND due_date IS NOT NULL AND due_date < ?",
    [$today]
);

$recentSales = all(
    'SELECT s.*, c.name AS client_name
     FROM sales s JOIN clients c ON c.id = s.client_id
     ORDER BY s.date DESC, s.id DESC LIMIT 8'
);

$debtors = all(
    "SELECT c.id, c.name, c.phone, SUM(cr.remaining) AS balance, COUNT(*) AS credits
     FROM credits cr JOIN clients c ON c.id = cr.client_id
     WHERE cr.status = 'open'
     GROUP BY c.id
     ORDER BY balance DESC LIMIT 6"
);

layout_start(t('dashboard'), 'dashboard');
?>

<div class="grid grid-4">
    <div class="card stat">
        <div class="stat-label"><?= e(t('sales_today')) ?></div>
        <div class="stat-value gold"><?= money($salesToday) ?></div>
        <div class="stat-foot"><?= $countToday ?> <?= e(t('sales')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('sales_month')) ?></div>
        <div class="stat-value"><?= money($salesMonth) ?></div>
        <div class="stat-foot"><?= e(date('m/Y')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('outstanding')) ?></div>
        <div class="stat-value" style="color: var(--orange)"><?= money($outstanding) ?></div>
        <div class="stat-foot"><?= $openCredits ?> <?= e(t('nav_credits')) ?><?= $overdueCount > 0 ? ' · ' . $overdueCount . ' ' . e(t('overdue')) : '' ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('payments_today')) ?></div>
        <div class="stat-value"><?= money($paymentsToday) ?></div>
        <div class="stat-foot"><?= $clientsCount ?> <?= e(t('clients_count')) ?> · <?= $productsCount ?> <?= e(t('products_count')) ?></div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head">
            <h2><?= e(t('recent_sales')) ?></h2>
            <a class="btn-link" href="<?= url('sales') ?>"><?= e(t('view_all')) ?></a>
        </div>
        <?php if (! $recentSales): ?>
            <div class="empty"><?= e(t('no_sales')) ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('date')) ?></th>
                        <th><?= e(t('client')) ?></th>
                        <th class="num"><?= e(t('total')) ?></th>
                        <th><?= e(t('status')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                        <tr>
                            <td><?= fr_date($sale['date']) ?></td>
                            <td><a href="<?= url('client', ['id' => $sale['client_id']]) ?>"><?= e($sale['client_name']) ?></a></td>
                            <td class="num"><?= money($sale['total']) ?></td>
                            <td>
                                <?php if ($sale['status'] === 'paid'): ?>
                                    <span class="badge badge-green"><?= e(t('status_paid')) ?></span>
                                <?php elseif ($sale['status'] === 'partial'): ?>
                                    <span class="badge badge-orange"><?= e(t('status_partial')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-red"><?= e(t('status_credit')) ?></span>
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
        <div class="card-head">
            <h2><?= e(t('top_debtors')) ?></h2>
            <a class="btn-link" href="<?= url('credits') ?>"><?= e(t('view_all')) ?></a>
        </div>
        <?php if (! $debtors): ?>
            <div class="empty"><?= e(t('no_debtors')) ?></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($debtors as $debtor): ?>
                    <div class="list-item">
                        <div class="grow">
                            <div class="name"><?= e($debtor['name']) ?></div>
                            <div class="sub"><?= e($debtor['phone'] ?: '—') ?> · <?= (int) $debtor['credits'] ?> <?= e(t('nav_credits')) ?></div>
                        </div>
                        <div class="num" style="font-weight:700"><?= money($debtor['balance']) ?></div>
                        <a class="btn btn-ghost btn-sm" href="<?= url('payments', ['new' => 1, 'client_id' => $debtor['id']]) ?>"><?= e(t('payment_new')) ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="toolbar">
    <a class="btn btn-primary" href="<?= url('sale_new') ?>">+ <?= e(t('sale_new')) ?></a>
    <a class="btn btn-ghost" href="<?= url('payment_new') ?>">+ <?= e(t('payment_new')) ?></a>
    <a class="btn btn-ghost" href="<?= url('clients', ['new' => 1]) ?>">+ <?= e(t('client_new')) ?></a>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('stats') ?>"><?= render_icon('activity') ?><?= e(t('nav_stats')) ?></a>
</div>

<?php layout_end(); ?>
