<?php
declare(strict_types=1);

require_login();

$id = (int) get('id');

$sale = one(
    'SELECT s.*, c.name AS client_name, c.phone AS client_phone, c.address AS client_address, u.name AS user_name
     FROM sales s
     JOIN clients c ON c.id = s.client_id
     LEFT JOIN users u ON u.id = s.user_id
     WHERE s.id = ?',
    [$id]
);

if (! $sale) {
    flash(t('no_results'), 'error');
    redirect('sales');
}

$items = all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id]);
$credits = all('SELECT * FROM credits WHERE sale_id = ? ORDER BY id', [$id]);

layout_start(t('sale_detail'), 'sales');
print_header(t('sale_detail') . ' #' . $id, fr_date($sale['date']));
?>

<div class="toolbar">
    <a class="btn btn-ghost" href="<?= url('sales') ?>"><?= render_icon('back') ?><?= e(t('back')) ?></a>
    <div class="spacer"></div>
    <?php if (is_admin()): ?>
        <a class="btn btn-ghost" href="<?= url('sale_edit', ['id' => $id]) ?>"><?= e(t('edit')) ?></a>
        <form method="post" action="<?= url('sale_delete') ?>" style="display:inline">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-danger" type="submit"
                    data-action="confirm" data-message="<?= e(t('confirm_delete_sale')) ?>">
                <?= e(t('delete')) ?>
            </button>
        </form>
    <?php endif; ?>
    <?php print_button(); ?>
    <?php pdf_button('sale', $id); ?>
    <a class="btn btn-primary" href="<?= url('ticket', ['type' => 'sale', 'id' => $id]) ?>" target="_blank"><?= e(t('print_ticket')) ?></a>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('sale_detail')) ?> #<?= (int) $sale['id'] ?></h2></div>
        <div class="card-body grid" style="gap:8px">
            <div class="row" style="display:flex;justify-content:space-between">
                <span class="muted"><?= e(t('date')) ?></span><strong><?= fr_date($sale['date']) ?></strong>
            </div>
            <div class="row" style="display:flex;justify-content:space-between">
                <span class="muted"><?= e(t('client')) ?></span>
                <a href="<?= url('client', ['id' => $sale['client_id']]) ?>"><strong><?= e($sale['client_name']) ?></strong></a>
            </div>
            <div class="row" style="display:flex;justify-content:space-between">
                <span class="muted"><?= e(t('phone')) ?></span><span><?= e($sale['client_phone'] ?: '—') ?></span>
            </div>
            <div class="row" style="display:flex;justify-content:space-between">
                <span class="muted"><?= e(t('payment_method')) ?></span><span><?= e(t($sale['payment_method'])) ?></span>
            </div>
            <div class="row" style="display:flex;justify-content:space-between">
                <span class="muted"><?= e(t('status')) ?></span>
                <span class="badge <?= $sale['status'] === 'paid' ? 'badge-green' : ($sale['status'] === 'partial' ? 'badge-orange' : 'badge-red') ?>">
                    <?= e(sale_status_label($sale['status'])) ?>
                </span>
            </div>
            <?php if ($sale['note']): ?>
                <div class="row" style="display:flex;justify-content:space-between">
                    <span class="muted"><?= e(t('notes')) ?></span><span><?= e($sale['note']) ?></span>
                </div>
            <?php endif; ?>
            <div class="row" style="display:flex;justify-content:space-between">
                <span class="muted"><?= e(t('nav_users')) ?></span><span><?= e($sale['user_name'] ?: '—') ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('total')) ?></h2></div>
        <div class="card-body totals">
            <div class="row"><span><?= e(t('subtotal')) ?></span><span><?= money($sale['subtotal']) ?></span></div>
            <div class="row"><span><?= e(t('discount')) ?></span><span>-<?= money($sale['discount']) ?></span></div>
            <div class="row grand"><span><?= e(t('total')) ?></span><span><?= money($sale['total']) ?></span></div>
            <div class="row"><span><?= e(t('paid')) ?></span><span style="color:var(--green)"><?= money($sale['paid']) ?></span></div>
            <div class="row"><span><?= e(t('remaining')) ?></span><strong style="color:var(--orange)"><?= money((float) $sale['total'] - (float) $sale['paid']) ?></strong></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('items')) ?></h2>
        <?php if (is_admin()): ?>
            <a class="btn btn-ghost btn-sm" href="<?= url('sale_items', ['sale_id' => $id]) ?>"><?= e(t('manage_items')) ?></a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e(t('product')) ?></th>
                <th class="num"><?= e(t('qty')) ?></th>
                <th class="num"><?= e(t('unit_price')) ?></th>
                <th class="num"><?= e(t('line_total')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $items): ?>
                <tr><td colspan="4" class="empty"><?= e(t('no_items')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td style="font-weight:600"><?= e(item_label($item)) ?></td>
                    <td class="num"><?= rtrim(rtrim(number_format((float) $item['qty'], 2, ',', ' '), '0'), ',') ?></td>
                    <td class="num"><?= money($item['unit_price']) ?></td>
                    <td class="num" style="font-weight:700"><?= money($item['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($credits): ?>
    <div class="card">
        <div class="card-head"><h2><?= e(t('nav_credits')) ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
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
                        <td><?= money($credit['amount']) ?></td>
                        <td class="num"><?= money($credit['paid']) ?></td>
                        <td class="num" style="font-weight:700"><?= money($credit['remaining']) ?></td>
                        <td><?= fr_date($credit['due_date']) ?></td>
                        <td>
                            <span class="badge <?= $credit['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>">
                                <?= e(credit_status_label($credit['status'])) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php layout_end(); ?>
