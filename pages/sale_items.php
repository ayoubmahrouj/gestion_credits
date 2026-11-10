<?php
declare(strict_types=1);

require_login();
require_admin();

$saleId = (int) get('sale_id');

$sale = one(
    'SELECT s.*, c.name AS client_name, c.phone AS client_phone, c.is_passenger
     FROM sales s JOIN clients c ON c.id = s.client_id
     WHERE s.id = ?',
    [$saleId]
);

if (! $sale) {
    flash(t('no_results'), 'error');
    redirect('sales');
}

$items = all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$saleId]);
$credit = one('SELECT * FROM credits WHERE sale_id = ?', [$saleId]);
$allocationCount = $credit
    ? (int) scalar('SELECT COUNT(*) FROM payment_allocations WHERE credit_id = ?', [(int) $credit['id']])
    : 0;
$locked = $allocationCount > 0;

$products = all('SELECT id, brand, name, price FROM products ORDER BY brand, name');

$editId = (int) get('edit');
$editing = null;

if ($editId > 0) {
    $editing = one('SELECT * FROM sale_items WHERE id = ? AND sale_id = ?', [$editId, $saleId]);

    if (! $editing) {
        flash(t('no_results'), 'error');
        redirect('sale_items', ['sale_id' => $saleId]);
    }
}

/* -------------------------------------------------------------------------
| Actions : create / update / delete
|-------------------------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($locked) {
        flash(t('cannot_edit_sale'), 'error');
        redirect('sale_items', ['sale_id' => $saleId]);
    }

    $action = (string) post('action');
    $itemId = post_int('item_id');

    if (in_array($action, ['create', 'update'], true)) {
        $productId = post_int('product_id');
        $name = trim((string) post('name'));
        $brand = trim((string) post('brand'));
        $qty = post_float('qty');
        $price = max(0, post_float('price'));

        if ($productId > 0) {
            $product = one('SELECT * FROM products WHERE id = ?', [$productId]);

            if ($product) {
                $name = (string) $product['name'];
                $brand = (string) $product['brand'];
            } else {
                $productId = 0;
            }
        }

        if ($name === '' || $qty <= 0) {
            flash(t('error_required'), 'error');
            redirect('sale_items', ['sale_id' => $saleId]);
        }

        if ($action === 'update') {
            $item = one('SELECT * FROM sale_items WHERE id = ? AND sale_id = ?', [$itemId, $saleId]);

            if (! $item) {
                flash(t('no_results'), 'error');
                redirect('sale_items', ['sale_id' => $saleId]);
            }
        }

        $lineTotal = round($qty * $price, 2);
        $errorKey = null;

        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($action === 'create') {
                q(
                    'INSERT INTO sale_items (sale_id, product_id, brand, product_name, qty, unit_price, total)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$saleId, $productId > 0 ? $productId : null, $brand, $name, $qty, $price, $lineTotal]
                );
            } else {
                q(
                    'UPDATE sale_items SET product_id = ?, brand = ?, product_name = ?, qty = ?, unit_price = ?, total = ?
                     WHERE id = ? AND sale_id = ?',
                    [$productId > 0 ? $productId : null, $brand, $name, $qty, $price, $lineTotal, $itemId, $saleId]
                );
            }

            $errorKey = recalculate_sale($saleId);

            if ($errorKey === null) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errorKey = 'error_required';
        }

        if ($errorKey !== null) {
            flash(t($errorKey), 'error');
            redirect('sale_items', ['sale_id' => $saleId]);
        }

        flash(t($action === 'create' ? 'item_added' : 'item_updated'));
        redirect('sale_items', ['sale_id' => $saleId]);
    }

    if ($action === 'delete') {
        $item = one('SELECT * FROM sale_items WHERE id = ? AND sale_id = ?', [$itemId, $saleId]);

        if (! $item) {
            flash(t('no_results'), 'error');
            redirect('sale_items', ['sale_id' => $saleId]);
        }

        $count = (int) scalar('SELECT COUNT(*) FROM sale_items WHERE sale_id = ?', [$saleId]);

        if ($count <= 1) {
            flash(t('at_least_one_item'), 'error');
            redirect('sale_items', ['sale_id' => $saleId]);
        }

        $errorKey = null;

        $pdo = db();
        $pdo->beginTransaction();

        try {
            q('DELETE FROM sale_items WHERE id = ? AND sale_id = ?', [$itemId, $saleId]);
            $errorKey = recalculate_sale($saleId);

            if ($errorKey === null) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errorKey = 'error_required';
        }

        if ($errorKey !== null) {
            flash(t($errorKey), 'error');
            redirect('sale_items', ['sale_id' => $saleId]);
        }

        flash(t('item_deleted'));
        redirect('sale_items', ['sale_id' => $saleId]);
    }
}

/* -------------------------------------------------------------------------
| Valeurs du formulaire
|-------------------------------------------------------------------------*/

$formProduct = $editing ? (int) ($editing['product_id'] ?? 0) : 0;
$formQty = $editing ? (string) $editing['qty'] : '1';
$formPrice = $editing ? (string) $editing['unit_price'] : '0';
$formName = $editing ? item_name($editing) : '';
$formBrand = $editing ? item_brand($editing) : '';

$remaining = (float) $sale['total'] - (float) $sale['paid'];

layout_start(t('manage_items') . ' · #' . $saleId, 'sales');
print_header(t('manage_items') . ' #' . $saleId, $sale['client_name']);
?>

<div class="toolbar">
    <a class="btn btn-ghost" href="<?= url('sale', ['id' => $saleId]) ?>"><?= render_icon('back') ?><?= e(t('back')) ?></a>
    <div class="spacer"></div>
    <?php pdf_button('sale', $saleId); ?>
    <?php print_button(); ?>
</div>

<?php if ($locked): ?>
    <div class="alert alert-info"><?= e(t('cannot_edit_sale')) ?></div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="card stat">
        <div class="stat-label"><?= e(t('client')) ?></div>
        <div class="stat-value" style="font-size:18px"><?= e($sale['client_name']) ?></div>
        <div class="stat-foot"><?= e($sale['client_phone'] ?: '—') ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('total')) ?></div>
        <div class="stat-value"><?= money($sale['total']) ?></div>
        <div class="stat-foot"><?= e(fr_date($sale['date'])) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('paid')) ?></div>
        <div class="stat-value" style="color:var(--green)"><?= money($sale['paid']) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label"><?= e(t('remaining')) ?></div>
        <div class="stat-value" style="color:<?= $remaining > 0 ? 'var(--orange)' : 'var(--green)' ?>"><?= money($remaining) ?></div>
        <div class="stat-foot"><?= e(sale_status_label($sale['status'])) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('items')) ?></h2>
        <span class="muted small"><?= count($items) ?> <?= e(t('items')) ?></span>
    </div>

    <?php if (! $items): ?>
        <div class="empty"><?= e(t('no_items')) ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('product')) ?></th>
                    <th class="num"><?= e(t('qty')) ?></th>
                    <th class="num"><?= e(t('unit_price')) ?></th>
                    <th class="num"><?= e(t('line_total')) ?></th>
                    <?php if (! $locked): ?><th class="actions"><?= e(t('actions')) ?></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td style="font-weight:600"><?= e(item_label($item)) ?></td>
                        <td class="num"><?= e(rtrim(rtrim(number_format((float) $item['qty'], 2, ',', ' '), '0'), ',')) ?></td>
                        <td class="num"><?= money($item['unit_price']) ?></td>
                        <td class="num" style="font-weight:700"><?= money($item['total']) ?></td>
                        <?php if (! $locked): ?>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="<?= url('sale_items', ['sale_id' => $saleId, 'edit' => $item['id']]) ?>"><?= e(t('edit')) ?></a>
                                <form method="post" action="<?= url('sale_items', ['sale_id' => $saleId]) ?>" style="display:inline">
                                    <input type="hidden" name="_token" value="<?= csrf() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit"
                                            data-action="confirm" data-message="<?= e(t('confirm_delete_item')) ?>">
                                        <?= e(t('delete')) ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (! $locked): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= e($editing ? t('item_edit') : t('item_new')) ?></h2>
        </div>
        <form class="card-body form" method="post" action="<?= url('sale_items', ['sale_id' => $saleId]) ?>" id="item-form">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="item_id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <div class="form-row-3">
                <label class="field">
                    <span><?= e(t('product_or_free')) ?></span>
                    <select name="product_id" id="item-product">
                        <option value="0" <?= $formProduct === 0 ? 'selected' : '' ?>>— <?= e(t('free_product')) ?> —</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int) $product['id'] ?>"
                                    data-price="<?= e((string) $product['price']) ?>"
                                    <?= $formProduct === (int) $product['id'] ? 'selected' : '' ?>>
                                <?= e(product_label($product)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= e(t('qty')) ?></span>
                    <input type="number" step="0.01" min="0.01" name="qty" id="item-qty" value="<?= e($formQty) ?>" required>
                </label>

                <label class="field">
                    <span><?= e(t('unit_price')) ?></span>
                    <input type="number" step="0.01" min="0" name="price" id="item-price" value="<?= e($formPrice) ?>">
                </label>
            </div>

            <div class="form-row" id="item-free-fields" <?= $formProduct === 0 ? '' : 'hidden' ?>>
                <label class="field">
                    <span><?= e(t('designation')) ?></span>
                    <input type="text" name="name" id="item-name" value="<?= e($formName) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('brand')) ?></span>
                    <input type="text" name="brand" value="<?= e($formBrand) ?>">
                </label>
            </div>

            <div class="totals" style="max-width:260px">
                <div class="row grand"><span><?= e(t('line_total')) ?></span><span id="item-total"><?= number_format((float) $formQty * (float) $formPrice, 2, ',', ' ') ?></span></div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <?php if ($editing): ?>
                    <a class="btn btn-ghost" href="<?= url('sale_items', ['sale_id' => $saleId]) ?>"><?= e(t('cancel')) ?></a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php layout_end(); ?>
