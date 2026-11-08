<?php
declare(strict_types=1);

require_login();

$type = get('type') === 'payment' ? 'payment' : 'sale';
$id = (int) get('id');
$autoPrint = get('autoprint') === '1';

if ($type === 'sale') {
    $sale = one(
        'SELECT s.*, c.name AS client_name, c.phone AS client_phone
         FROM sales s JOIN clients c ON c.id = s.client_id WHERE s.id = ?',
        [$id]
    );

    if (! $sale) {
        flash(t('no_results'), 'error');
        redirect('sales');
    }

    $items = all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id]);
    $remaining = (float) $sale['total'] - (float) $sale['paid'];
} else {
    $payment = one(
        'SELECT p.*, c.name AS client_name, c.phone AS client_phone
         FROM payments p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
        [$id]
    );

    if (! $payment) {
        flash(t('no_results'), 'error');
        redirect('payments');
    }

    $paymentBalance = client_balance((int) $payment['client_id']);
    $allocations = all(
        'SELECT pa.amount, cr.id, cr.amount AS credit_amount FROM payment_allocations pa
         JOIN credits cr ON cr.id = pa.credit_id WHERE pa.payment_id = ?',
        [$id]
    );
}
?>
<!doctype html>
<html lang="<?= locale() ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($type === 'sale' ? t('print_ticket') : t('payment_receipt')) ?> #<?= $id ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/app.css">
    <style>
        body { background: #1a1d23; }
        @media print { body { background: #fff; } }
    </style>
</head>
<body>

<div class="ticket-actions no-print" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <button class="btn btn-primary" type="button" onclick="window.print()"><?= e(t('print')) ?></button>
    <?php if ($type === 'sale'): ?>
        <a class="btn btn-ghost" href="<?= url('sales') ?>"><?= e(t('sales')) ?></a>
        <a class="btn btn-ghost" href="<?= url('sale_new') ?>">+ <?= e(t('sale_new')) ?></a>
    <?php else: ?>
        <a class="btn btn-ghost" href="<?= url('payments') ?>"><?= e(t('payments')) ?></a>
        <a class="btn btn-ghost" href="<?= url('payment_new') ?>">+ <?= e(t('payment_new')) ?></a>
    <?php endif; ?>
</div>

<div class="ticket-page">
    <h2><?= e(setting('shop_name', APP_NAME)) ?></h2>

    <?php if (setting('shop_address') !== ''): ?>
        <div class="ticket-center"><?= e(setting('shop_address')) ?></div>
    <?php endif; ?>
    <?php if (setting('shop_phone') !== ''): ?>
        <div class="ticket-center">Tél : <?= e(setting('shop_phone')) ?></div>
    <?php endif; ?>

    <div class="ticket-sep"></div>

    <div class="ticket-center" style="font-weight:bold">
        <?= e($type === 'sale' ? t('print_ticket') : t('payment_receipt')) ?>
    </div>
    <div class="ticket-line">
        <span>N° <?= $id ?></span>
        <span><?= fr_date($type === 'sale' ? $sale['date'] : $payment['date']) ?></span>
    </div>
    <div class="ticket-line">
        <span><?= e(t('client')) ?> : <?= e($type === 'sale' ? $sale['client_name'] : $payment['client_name']) ?></span>
    </div>
    <?php $phone = $type === 'sale' ? $sale['client_phone'] : $payment['client_phone']; ?>
    <?php if ($phone): ?>
        <div class="ticket-line"><span>Tél : <?= e($phone) ?></span></div>
    <?php endif; ?>

    <div class="ticket-sep"></div>

    <?php if ($type === 'sale'): ?>
        <table class="ticket-table">
            <thead>
            <tr>
                <th><?= e(t('product')) ?></th>
                <th class="num"><?= e(t('qty')) ?></th>
                <th class="num"><?= e(t('total')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td class="num"><?= rtrim(rtrim(number_format((float) $item['qty'], 2, ',', ' '), '0'), ',') ?></td>
                    <td class="num"><?= number_format((float) $item['total'], 2, ',', ' ') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="ticket-sep"></div>

        <div class="ticket-line"><span><?= e(t('subtotal')) ?></span><span><?= number_format((float) $sale['subtotal'], 2, ',', ' ') ?></span></div>
        <?php if ((float) $sale['discount'] > 0): ?>
            <div class="ticket-line"><span><?= e(t('discount')) ?></span><span>-<?= number_format((float) $sale['discount'], 2, ',', ' ') ?></span></div>
        <?php endif; ?>
        <div class="ticket-line" style="font-weight:bold;font-size:14px">
            <span><?= e(t('total')) ?></span><span><?= number_format((float) $sale['total'], 2, ',', ' ') ?> <?= CURRENCY ?></span>
        </div>
        <div class="ticket-line"><span><?= e(t('paid')) ?></span><span><?= number_format((float) $sale['paid'], 2, ',', ' ') ?></span></div>
        <?php if ($remaining > 0.009): ?>
            <div class="ticket-line" style="font-weight:bold">
                <span><?= e(t('remaining')) ?></span><span><?= number_format($remaining, 2, ',', ' ') ?> <?= CURRENCY ?></span>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="ticket-line" style="font-size:14px;font-weight:bold">
            <span><?= e(t('amount')) ?></span>
            <span><?= number_format((float) $payment['amount'], 2, ',', ' ') ?> <?= CURRENCY ?></span>
        </div>
        <div class="ticket-line"><span><?= e(t('method')) ?></span><span><?= e(t($payment['method'])) ?></span></div>
        <div class="ticket-line"><span><?= e(t('balance')) ?> (<?= e(t('remaining')) ?>)</span><span><?= number_format($paymentBalance, 2, ',', ' ') ?> <?= CURRENCY ?></span></div>
        <?php if ($payment['note']): ?>
            <div class="ticket-line"><span><?= e(t('notes')) ?></span><span><?= e($payment['note']) ?></span></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="ticket-sep"></div>

    <div class="ticket-center"><?= e(setting('shop_footer', t('thanks'))) ?></div>
    <div class="ticket-center" style="margin-top:4px"><?= e(date('d/m/Y H:i')) ?></div>
</div>

<?php if ($autoPrint): ?>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
<?php endif; ?>
</body>
</html>
