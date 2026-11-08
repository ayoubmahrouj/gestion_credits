<?php
declare(strict_types=1);

require_login();

$errors = [];

/* -------------------------------------------------------------------------
| Enregistrement de la vente
|-------------------------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $clientId = post_int('client_id');
    $date = (string) post('date', today());
    $discount = max(0, post_float('discount'));
    $paidInput = max(0, post_float('paid'));
    $method = in_array(post('method'), ['cash', 'card', 'transfer'], true) ? post('method') : 'cash';
    $note = trim((string) post('note'));

    $productIds = (array) post('product_id', []);
    $qtys = (array) post('qty', []);
    $prices = (array) post('price', []);

    $client = one('SELECT * FROM clients WHERE id = ? AND is_active = 1', [$clientId]);

    if (! $client) {
        $errors[] = t('error_required');
    }

    $lines = [];

    foreach ($productIds as $index => $productId) {
        $productId = (int) $productId;
        $qty = (float) ($qtys[$index] ?? 0);
        $price = (float) str_replace(',', '.', (string) ($prices[$index] ?? 0));

        if ($productId <= 0 || $qty <= 0) {
            continue;
        }

        $product = one('SELECT * FROM products WHERE id = ? AND is_active = 1', [$productId]);

        if (! $product) {
            continue;
        }

        $lines[] = [
            'product_id' => $productId,
            'name' => ($product['brand'] !== '' ? $product['brand'] . ' — ' : '') . $product['name'],
            'qty' => $qty,
            'price' => $price,
            'total' => round($qty * $price, 2),
        ];
    }

    if (! $lines) {
        $errors[] = t('error_required');
    }

    if (! $errors) {
        $subtotal = round(array_sum(array_column($lines, 'total')), 2);
        $total = round(max(0, $subtotal - $discount), 2);
        $paid = min($paidInput, $total);
        $remaining = round($total - $paid, 2);

        if ($remaining <= 0.009) {
            $status = 'paid';
            $paymentMethod = $method;
        } elseif ($paid > 0) {
            $status = 'partial';
            $paymentMethod = $method;
        } else {
            $status = 'credit';
            $paymentMethod = 'credit';
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            q(
                'INSERT INTO sales (client_id, user_id, date, subtotal, discount, total, paid, payment_method, status, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$clientId, (int) (user()['id'] ?? 0), $date, $subtotal, $discount, $total, $paid, $paymentMethod, $status, $note]
            );

            $saleId = (int) $pdo->lastInsertId();

            foreach ($lines as $line) {
                q(
                    'INSERT INTO sale_items (sale_id, product_id, product_name, qty, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)',
                    [$saleId, $line['product_id'], $line['name'], $line['qty'], $line['price'], $line['total']]
                );

                q('UPDATE products SET stock = stock - ? WHERE id = ?', [$line['qty'], $line['product_id']]);
            }

            if ($remaining > 0.009) {
                $days = max(1, (int) setting('credit_alert_days', '30'));
                $dueDate = date('Y-m-d', strtotime($date . ' +' . $days . ' days'));

                q(
                    'INSERT INTO credits (sale_id, client_id, amount, paid, remaining, due_date, status)
                     VALUES (?, ?, ?, 0, ?, ?, ?)',
                    [$saleId, $clientId, $remaining, $remaining, $dueDate, 'open']
                );
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = $exception->getMessage();
        }

        if (! $errors) {
            flash(t('saved'));
            redirect('ticket', ['type' => 'sale', 'id' => $saleId, 'autoprint' => 1]);
        }
    }
}

/* -------------------------------------------------------------------------
| Données du formulaire
|-------------------------------------------------------------------------*/

$clients = all('SELECT id, name, phone FROM clients WHERE is_active = 1 ORDER BY name COLLATE NOCASE');
$products = all('SELECT id, brand, name, price, stock FROM products WHERE is_active = 1 ORDER BY brand COLLATE NOCASE, name COLLATE NOCASE');

$selectedClient = (int) (post('client_id') ?: get('client_id'));
$selectedDate = (string) (post('date') ?: today());

layout_start(t('sale_new'), 'sales');
?>

<div class="toolbar">
    <a class="btn btn-ghost" href="<?= url('sales') ?>"><?= render_icon('back') ?><?= e(t('back')) ?></a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('sale_new') ?>" id="sale-form" class="grid" style="gap:18px">
    <input type="hidden" name="_token" value="<?= csrf() ?>">

    <div class="card">
        <div class="card-head"><h2><?= e(t('sale_new')) ?></h2></div>
        <div class="card-body grid" style="gap:16px">
            <div class="form-row-3">
                <label class="field">
                    <span><?= e(t('client')) ?> *</span>
                    <div style="display:flex;gap:8px">
                        <select name="client_id" id="sale-client" required style="flex:1">
                            <option value="">— <?= e(t('client')) ?> —</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= $selectedClient === (int) $client['id'] ? 'selected' : '' ?>>
                                    <?= e($client['name']) ?><?= $client['phone'] ? ' · ' . e($client['phone']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="quick-add" type="button" data-action="modal-open" data-target="quick-client-modal" title="<?= e(t('client_new')) ?>">+</button>
                    </div>
                </label>

                <label class="field">
                    <span><?= e(t('date')) ?></span>
                    <input type="date" name="date" value="<?= e($selectedDate) ?>" required>
                </label>

                <label class="field">
                    <span><?= e(t('payment_method')) ?></span>
                    <select name="method">
                        <option value="cash"><?= e(t('cash')) ?></option>
                        <option value="card"><?= e(t('card')) ?></option>
                        <option value="transfer"><?= e(t('transfer')) ?></option>
                    </select>
                </label>
            </div>

            <label class="field">
                <span><?= e(t('notes')) ?></span>
                <input type="text" name="note" value="<?= e(post('note')) ?>" placeholder="<?= e(t('notes')) ?>">
            </label>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2><?= e(t('items')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-add-line>+ <?= e(t('add_line')) ?></button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th style="min-width:250px"><?= e(t('product')) ?></th>
                    <th style="width:90px"><?= e(t('qty')) ?></th>
                    <th style="width:140px"><?= e(t('unit_price')) ?></th>
                    <th class="num" style="width:130px"><?= e(t('line_total')) ?></th>
                    <th style="width:50px"></th>
                </tr>
                </thead>
                <tbody id="sale-lines">
                <?php
                $oldIds = (array) post('product_id', []);
                $oldQtys = (array) post('qty', []);
                $oldPrices = (array) post('price', []);

                foreach ($oldIds as $index => $oldId):
                    $oldId = (int) $oldId;
                    $oldQty = (float) ($oldQtys[$index] ?? 1);
                    $oldPrice = (float) ($oldPrices[$index] ?? 0);
                    ?>
                    <tr class="sale-line">
                        <td>
                            <div style="display:flex;gap:8px">
                                <select name="product_id[]" class="line-product" style="flex:1">
                                    <option value="">— <?= e(t('product')) ?> —</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= (int) $product['id'] ?>"
                                                data-price="<?= e((string) $product['price']) ?>"
                                                <?= $oldId === (int) $product['id'] ? 'selected' : '' ?>>
                                            <?= e(($product['brand'] !== '' ? $product['brand'] . ' — ' : '') . $product['name']) ?>
                                            (<?= (int) $product['stock'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="quick-add" type="button" data-action="modal-open" data-target="quick-product-modal" title="<?= e(t('product_new')) ?>">+</button>
                            </div>
                        </td>
                        <td><input type="number" step="0.01" min="0.01" name="qty[]" class="line-qty" value="<?= e((string) $oldQty) ?>" style="width:100%"></td>
                        <td><input type="number" step="0.01" min="0" name="price[]" class="line-price" value="<?= e((string) $oldPrice) ?>" style="width:100%"></td>
                        <td class="num line-total">0,00</td>
                        <td><button class="btn btn-ghost btn-sm" type="button" data-remove-line>×</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-2">
        <div></div>
        <div class="card">
            <div class="card-body totals">
                <div class="row"><span><?= e(t('subtotal')) ?></span><strong id="sale-subtotal">0,00</strong></div>
                <div class="row">
                    <span><?= e(t('discount')) ?></span>
                    <input type="number" step="0.01" min="0" name="discount" id="sale-discount" value="<?= e(post('discount', '0')) ?>" style="width:110px;text-align:end">
                </div>
                <div class="row grand"><span><?= e(t('total')) ?></span><span id="sale-total">0,00</span></div>
                <input type="hidden" name="total" id="sale-total-input" value="0">
                <div class="row">
                    <span><?= e(t('paid_now')) ?></span>
                    <input type="number" step="0.01" min="0" name="paid" id="sale-paid" data-auto="1" value="<?= e(post('paid', '0')) ?>" style="width:130px;text-align:end">
                </div>
                <div class="row"><span><?= e(t('remaining')) ?></span><strong id="sale-remaining" style="color:var(--orange)">0,00</strong></div>
                <button class="btn btn-primary btn-block" type="submit" style="margin-top:8px"><?= e(t('sale_submit')) ?></button>
            </div>
        </div>
    </div>
</form>

<template id="sale-line-template">
    <tr class="sale-line">
        <td>
            <div style="display:flex;gap:8px">
                <select name="product_id[]" class="line-product" style="flex:1">
                    <option value="">— <?= e(t('product')) ?> —</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>" data-price="<?= e((string) $product['price']) ?>">
                            <?= e(($product['brand'] !== '' ? $product['brand'] . ' — ' : '') . $product['name']) ?>
                            (<?= (int) $product['stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="quick-add" type="button" data-action="modal-open" data-target="quick-product-modal" title="<?= e(t('product_new')) ?>">+</button>
            </div>
        </td>
        <td><input type="number" step="0.01" min="0.01" name="qty[]" class="line-qty" value="1" style="width:100%"></td>
        <td><input type="number" step="0.01" min="0" name="price[]" class="line-price" value="0" style="width:100%"></td>
        <td class="num line-total">0,00</td>
        <td><button class="btn btn-ghost btn-sm" type="button" data-remove-line>×</button></td>
    </tr>
</template>

<!-- Ajout rapide : client -->
<div class="modal" id="quick-client-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box" style="max-width:460px">
        <div class="modal-head">
            <h2><?= e(t('client_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" data-quick-form="client_quick" data-target="sale-client">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <label class="field">
                <span><?= e(t('name')) ?> *</span>
                <input type="text" name="name" required>
            </label>
            <label class="field">
                <span><?= e(t('phone')) ?></span>
                <input type="text" name="phone">
            </label>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button class="btn btn-ghost" type="button" data-action="modal-close"><?= e(t('cancel')) ?></button>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Ajout rapide : produit -->
<div class="modal" id="quick-product-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box" style="max-width:520px">
        <div class="modal-head">
            <h2><?= e(t('product_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" data-quick-form="product_quick" data-target="__line-product__">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <div class="form-row">
                <label class="field">
                    <span><?= e(t('brand')) ?></span>
                    <input type="text" name="brand">
                </label>
                <label class="field">
                    <span><?= e(t('designation')) ?> *</span>
                    <input type="text" name="name" required>
                </label>
            </div>
            <div class="form-row">
                <label class="field">
                    <span><?= e(t('price')) ?> *</span>
                    <input type="number" step="0.01" min="0" name="price" required>
                </label>
                <label class="field">
                    <span><?= e(t('stock')) ?></span>
                    <input type="number" step="1" min="0" name="stock" value="0">
                </label>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button class="btn btn-ghost" type="button" data-action="modal-close"><?= e(t('cancel')) ?></button>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php layout_end(); ?>
