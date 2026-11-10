<?php
declare(strict_types=1);

require_login();
require_admin();

$id = (int) get('id');

$sale = one(
    'SELECT s.*, c.name AS client_name FROM sales s JOIN clients c ON c.id = s.client_id WHERE s.id = ?',
    [$id]
);

if (! $sale) {
    flash(t('no_results'), 'error');
    redirect('sales');
}

$items = all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id]);
$credit = one('SELECT * FROM credits WHERE sale_id = ?', [$id]);
$allocationCount = $credit
    ? (int) scalar('SELECT COUNT(*) FROM payment_allocations WHERE credit_id = ?', [(int) $credit['id']])
    : 0;
$locked = $allocationCount > 0;

$clients = all('SELECT id, name FROM clients WHERE is_active = 1 AND is_passenger = 0 ORDER BY name');
$currentClient = one('SELECT id, name, is_active FROM clients WHERE id = ?', [(int) $sale['client_id']]);
$extraClient = null;

if ($currentClient) {
    $knownIds = array_map('intval', array_column($clients, 'id'));

    if (! in_array((int) $currentClient['id'], $knownIds, true)) {
        $extraClient = $currentClient;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $date = (string) (post('date') ?: today());
    $note = trim((string) post('note'));

    if ($locked) {
        q('UPDATE sales SET date = ?, note = ? WHERE id = ?', [$date, $note, $id]);
        flash(t('sale_updated'));
        redirect('sale', ['id' => $id]);
    }

    $clientId = post_int('client_id');
    $discount = max(0, post_float('discount'));
    $paidInput = max(0, post_float('paid'));
    $method = in_array(post('method'), ['cash', 'card', 'transfer'], true) ? post('method') : 'cash';

    $client = one('SELECT * FROM clients WHERE id = ? AND (is_active = 1 OR id = ?)', [$clientId, (int) $sale['client_id']]);

    if (! $client) {
        $errors[] = t('error_required');
    }

    $names = (array) post('name', []);
    $productIds = (array) post('product_id', []);
    $brands = (array) post('brand', []);
    $qtys = (array) post('qty', []);
    $prices = (array) post('price', []);

    $lines = [];

    foreach ($names as $index => $rawName) {
        $name = trim((string) $rawName);
        $brand = trim((string) ($brands[$index] ?? ''));
        $qty = (float) ($qtys[$index] ?? 0);
        $price = max(0, (float) str_replace(',', '.', (string) ($prices[$index] ?? 0)));
        $productId = (int) ($productIds[$index] ?? 0);

        if ($name === '' || $qty <= 0) {
            continue;
        }

        if ($productId > 0) {
            $product = one('SELECT * FROM products WHERE id = ?', [$productId]);

            if ($product) {
                $brand = (string) $product['brand'];
                $name = (string) $product['name'];
            } else {
                $productId = 0;
            }
        }

        // Ligne libre : le champ affiche « Désignation — Marque » ; on sépare
        // la marque pour la stocker dans sa colonne.
        if ($productId <= 0 && $brand !== '' && str_ends_with($name, ' — ' . $brand)) {
            $name = trim(substr($name, 0, -strlen(' — ' . $brand)));
        }

        $lines[] = [
            'product_id' => $productId > 0 ? $productId : null,
            'brand' => $brand,
            'name' => $name,
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
        $isPassenger = (int) ($client['is_passenger'] ?? 0) === 1;
        $paid = $isPassenger ? $total : min($paidInput, $total);
        $remaining = round($total - $paid, 2);

        if ($remaining <= 0.009) {
            $status = 'paid';
            $paymentMethod = $method;
            $remaining = 0.0;
        } elseif ($paid > 0) {
            $status = 'partial';
            $paymentMethod = $method;
        } else {
            $status = 'credit';
            $paymentMethod = 'credit';
        }

        if ($remaining > 0 && $isPassenger) {
            $errors[] = t('error_required');
        }
    }

    if (! $errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            q(
                'UPDATE sales SET client_id = ?, date = ?, subtotal = ?, discount = ?, total = ?, paid = ?, payment_method = ?, status = ?, note = ? WHERE id = ?',
                [$clientId, $date, $subtotal, $discount, $total, $paid, $paymentMethod, $status, $note, $id]
            );

            q('DELETE FROM sale_items WHERE sale_id = ?', [$id]);

            foreach ($lines as $line) {
                q(
                    'INSERT INTO sale_items (sale_id, product_id, brand, product_name, qty, unit_price, total) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $line['product_id'], $line['brand'], $line['name'], $line['qty'], $line['price'], $line['total']]
                );
            }

            if ($remaining > 0.009) {
                if ($credit) {
                    $creditPaid = (float) $credit['paid'];
                    $creditRemaining = round(max(0, $remaining - $creditPaid), 2);

                    q(
                        'UPDATE credits SET amount = ?, remaining = ?, status = ? WHERE id = ?',
                        [$remaining, $creditRemaining, $creditRemaining <= 0.009 ? 'paid' : 'open', $credit['id']]
                    );
                } else {
                    $days = max(1, (int) setting('credit_alert_days', '30'));
                    $dueDate = date('Y-m-d', strtotime($date . ' +' . $days . ' days'));

                    q(
                        'INSERT INTO credits (sale_id, client_id, amount, paid, remaining, due_date, status)
                         VALUES (?, ?, ?, 0, ?, ?, ?)',
                        [$id, $clientId, $remaining, $remaining, $dueDate, 'open']
                    );
                }
            } elseif ($credit) {
                q('DELETE FROM credits WHERE id = ?', [(int) $credit['id']]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = $exception->getMessage();
        }

        if (! $errors) {
            flash(t('sale_updated'));
            redirect('sale', ['id' => $id]);
        }
    }

    $sale['date'] = $date;
    $sale['note'] = $note;
}

$selectedDate = (string) ($sale['date'] ?? today());
$selectedClient = (int) ($sale['client_id'] ?? 0);

layout_start(t('sale_edit'), 'sales');
print_header(t('sale_edit') . ' #' . $id, fr_date($selectedDate));
?>

<div class="toolbar">
    <a class="btn btn-ghost" href="<?= url('sale', ['id' => $id]) ?>"><?= render_icon('back') ?><?= e(t('back')) ?></a>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('ticket', ['type' => 'sale', 'id' => $id]) ?>" target="_blank"><?= e(t('ticket')) ?></a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<?php if ($locked): ?>
    <div class="alert alert-info"><?= e(t('cannot_edit_sale')) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('sale_edit', ['id' => $id]) ?>" id="<?= $locked ? 'sale-locked-form' : 'sale-form' ?>" class="grid" style="gap:18px">
    <input type="hidden" name="_token" value="<?= csrf() ?>">

    <div class="card">
        <div class="card-head"><h2><?= e(t('sale_edit')) ?> #<?= $id ?></h2></div>
        <div class="card-body grid" style="gap:16px">
            <div class="form-row-3">
                <label class="field">
                    <span><?= e(t('client')) ?></span>
                    <?php if ($locked): ?>
                        <input type="text" value="<?= e($sale['client_name']) ?>" disabled>
                    <?php else: ?>
                        <select name="client_id" required>
                            <?php if ($extraClient): ?>
                                <option value="<?= (int) $extraClient['id'] ?>" selected>
                                    <?= e($extraClient['name']) ?><?= (int) $extraClient['is_active'] === 0 ? ' (' . e(t('inactive')) . ')' : '' ?>
                                </option>
                            <?php endif; ?>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= $selectedClient === (int) $client['id'] ? 'selected' : '' ?>>
                                    <?= e($client['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span><?= e(t('date')) ?></span>
                    <input type="date" name="date" value="<?= e($selectedDate) ?>" required>
                </label>

                <?php if (! $locked): ?>
                    <label class="field">
                        <span><?= e(t('payment_method')) ?></span>
                        <select name="method">
                            <option value="cash" <?= $sale['payment_method'] === 'cash' ? 'selected' : '' ?>><?= e(t('cash')) ?></option>
                            <option value="card" <?= $sale['payment_method'] === 'card' ? 'selected' : '' ?>><?= e(t('card')) ?></option>
                            <option value="transfer" <?= $sale['payment_method'] === 'transfer' ? 'selected' : '' ?>><?= e(t('transfer')) ?></option>
                        </select>
                    </label>
                <?php endif; ?>
            </div>

            <label class="field">
                <span><?= e(t('notes')) ?></span>
                <input type="text" name="note" value="<?= e($sale['note'] ?? '') ?>">
            </label>
        </div>
    </div>

    <?php if ($locked): ?>
        <div class="card">
            <div class="card-head"><h2><?= e(t('items')) ?></h2></div>
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
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="font-weight:600"><?= e(item_label($item)) ?></td>
                            <td class="num"><?= rtrim(rtrim(number_format((float) $item['qty'], 2, ',', ' '), '0'), ',') ?></td>
                            <td class="num"><?= money($item['unit_price']) ?></td>
                            <td class="num"><?= money($item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-head">
                <h2><?= e(t('items')) ?></h2>
                <button class="btn btn-ghost btn-sm" type="button" data-add-line>+ <?= e(t('add_line')) ?></button>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th style="min-width:250px"><?= e(t('product_or_free')) ?></th>
                        <th style="width:90px"><?= e(t('qty')) ?></th>
                        <th style="width:140px"><?= e(t('unit_price')) ?></th>
                        <th class="num" style="width:130px"><?= e(t('line_total')) ?></th>
                        <th style="width:50px"></th>
                    </tr>
                    </thead>
                    <tbody id="sale-lines">
                    <?php foreach ($items as $item): ?>
                        <tr class="sale-line">
                            <td>
                                <input type="text" name="name[]" value="<?= e(item_label($item)) ?>" required>
                                <input type="hidden" name="brand[]" value="<?= e(item_brand($item)) ?>">
                                <input type="hidden" name="product_id[]" value="<?= (int) $item['product_id'] ?>">
                            </td>
                            <td><input type="number" step="0.01" min="0.01" name="qty[]" class="line-qty" value="<?= e((string) $item['qty']) ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="price[]" class="line-price" value="<?= e((string) $item['unit_price']) ?>"></td>
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
                        <input type="number" step="0.01" min="0" name="discount" id="sale-discount" value="<?= e((string) $sale['discount']) ?>" style="width:110px;text-align:end">
                    </div>
                    <div class="row grand"><span><?= e(t('total')) ?></span><span id="sale-total">0,00</span></div>
                    <input type="hidden" name="total" id="sale-total-input" value="0">
                    <div class="row">
                        <span><?= e(t('paid_now')) ?></span>
                        <input type="number" step="0.01" min="0" name="paid" id="sale-paid" data-auto="0" value="<?= e((string) $sale['paid']) ?>" style="width:130px;text-align:end">
                    </div>
                    <div class="row"><span><?= e(t('remaining')) ?></span><strong id="sale-remaining" style="color:var(--orange)">0,00</strong></div>
                    <button class="btn btn-primary btn-block" type="submit" style="margin-top:8px"><?= e(t('save')) ?></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($locked): ?>
        <div>
            <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
        </div>
    <?php endif; ?>
</form>

<?php if (! $locked): ?>
    <template id="sale-line-template">
        <tr class="sale-line">
            <td>
                <input type="text" name="name[]" value="" placeholder="<?= e(t('pos_product_ph')) ?>" required>
                <input type="hidden" name="brand[]" value="">
                <input type="hidden" name="product_id[]" value="0">
            </td>
            <td><input type="number" step="0.01" min="0.01" name="qty[]" class="line-qty" value="1"></td>
            <td><input type="number" step="0.01" min="0" name="price[]" class="line-price" value="0"></td>
            <td class="num line-total">0,00</td>
            <td><button class="btn btn-ghost btn-sm" type="button" data-remove-line>×</button></td>
        </tr>
    </template>
<?php endif; ?>

<!-- Suppression (admin) -->
<form method="post" action="<?= url('sale_delete') ?>" style="margin-top:4px">
    <input type="hidden" name="_token" value="<?= csrf() ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn-danger" type="submit"
            data-action="confirm" data-message="<?= e(t('confirm_delete_sale')) ?>">
        <?= e(t('delete')) ?>
    </button>
</form>

<?php layout_end(); ?>
