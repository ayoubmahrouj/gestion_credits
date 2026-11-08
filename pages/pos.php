<?php
declare(strict_types=1);

require_login();

$errors = [];

/* -------------------------------------------------------------------------
| Encaissement
|-------------------------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $clientId = post_int('client_id');
    $client = one('SELECT * FROM clients WHERE id = ? AND is_active = 1', [$clientId]);

    if (! $client) {
        $client = passenger_client();
        $clientId = (int) $client['id'];
    }

    $isPassenger = (int) ($client['is_passenger'] ?? 0) === 1;
    $date = (string) (post('date') ?: today());
    $method = in_array(post('method'), ['cash', 'card', 'transfer'], true) ? post('method') : 'cash';
    $note = trim((string) post('note'));
    $paidInput = max(0, post_float('paid'));

    $names = (array) post('name', []);
    $productIds = (array) post('product_id', []);
    $qtys = (array) post('qty', []);
    $prices = (array) post('price', []);

    $lines = [];

    foreach ($names as $index => $rawName) {
        $name = trim((string) $rawName);
        $qty = (float) ($qtys[$index] ?? 0);
        $price = max(0, (float) str_replace(',', '.', (string) ($prices[$index] ?? 0)));
        $productId = (int) ($productIds[$index] ?? 0);

        if ($name === '' || $qty <= 0) {
            continue;
        }

        if ($productId > 0) {
            $product = one('SELECT * FROM products WHERE id = ? AND is_active = 1', [$productId]);

            if ($product) {
                $name = ($product['brand'] !== '' ? $product['brand'] . ' — ' : '') . $product['name'];
            } else {
                $productId = 0;
            }
        }

        $lines[] = [
            'product_id' => $productId > 0 ? $productId : null,
            'name' => $name,
            'qty' => $qty,
            'price' => $price,
            'total' => round($qty * $price, 2),
        ];
    }

    if (! $lines) {
        $errors[] = t('pos_empty_cart');
    }

    if (! $errors) {
        $subtotal = round(array_sum(array_column($lines, 'total')), 2);
        $total = $subtotal;
        $paid = $isPassenger ? $total : min($paidInput, $total);
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
                 VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
                [$clientId, (int) (user()['id'] ?? 0), $date, $subtotal, $total, $paid, $paymentMethod, $status, $note]
            );

            $saleId = (int) $pdo->lastInsertId();

            foreach ($lines as $line) {
                q(
                    'INSERT INTO sale_items (sale_id, product_id, product_name, qty, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)',
                    [$saleId, $line['product_id'], $line['name'], $line['qty'], $line['price'], $line['total']]
                );
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

$passenger = passenger_client();
$clients = all('SELECT id, name, phone FROM clients WHERE is_active = 1 AND is_passenger = 0 ORDER BY name COLLATE NOCASE');
$products = all('SELECT id, brand, name, price FROM products WHERE is_active = 1 ORDER BY brand COLLATE NOCASE, name COLLATE NOCASE');

$selectedClient = (int) (post('client_id') ?: $passenger['id']);
$productData = [];

foreach ($products as $product) {
    $label = ($product['brand'] !== '' ? $product['brand'] . ' — ' : '') . $product['name'];
    $productData[] = ['label' => $label, 'id' => (int) $product['id'], 'price' => (float) $product['price']];
}

layout_start(t('pos'), 'pos');
?>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('pos') ?>" id="pos-form" class="pos" autocomplete="off">
    <input type="hidden" name="_token" value="<?= csrf() ?>">

    <div class="card pos-main">
        <div class="card-head">
            <h2><?= e(t('pos')) ?></h2>
            <span class="muted small"><?= e(t('pos_hint')) ?></span>
        </div>

        <div class="card-body grid" style="gap:14px">
            <div class="form-row-3">
                <label class="field">
                    <span><?= e(t('client')) ?></span>
                    <div style="display:flex;gap:8px">
                        <select name="client_id" id="pos-client" style="flex:1">
                            <option value="<?= (int) $passenger['id'] ?>" <?= $selectedClient === (int) $passenger['id'] ? 'selected' : '' ?>>
                                <?= e(t('passenger')) ?>
                            </option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= $selectedClient === (int) $client['id'] ? 'selected' : '' ?>>
                                    <?= e($client['name']) ?><?= $client['phone'] ? ' · ' . e($client['phone']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="quick-add" type="button" data-action="modal-open" data-target="pos-client-modal" title="<?= e(t('client_new')) ?>">+</button>
                    </div>
                </label>

                <label class="field">
                    <span><?= e(t('date')) ?></span>
                    <input type="date" name="date" value="<?= e(post('date') ?: today()) ?>">
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

            <div class="pos-search">
                <div class="pos-search-row">
                    <label class="field" style="flex:1">
                        <span><?= e(t('product_or_free')) ?></span>
                        <input type="text" id="pos-product" list="pos-products" placeholder="<?= e(t('pos_product_ph')) ?>">
                    </label>
                    <label class="field pos-qty-field">
                        <span><?= e(t('qty')) ?></span>
                        <input type="number" id="pos-qty" step="0.01" min="0.01" value="1">
                    </label>
                    <label class="field pos-price-field">
                        <span><?= e(t('unit_price')) ?></span>
                        <input type="number" id="pos-price" step="0.01" min="0" value="0">
                    </label>
                    <button class="btn btn-primary pos-add" type="button" id="pos-add">+ <?= e(t('add_to_cart')) ?></button>
                </div>

                <datalist id="pos-products">
                    <?php foreach ($productData as $product): ?>
                        <option value="<?= e($product['label']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table pos-table">
                <thead>
                <tr>
                    <th><?= e(t('product')) ?></th>
                    <th class="num" style="width:100px"><?= e(t('qty')) ?></th>
                    <th class="num" style="width:130px"><?= e(t('unit_price')) ?></th>
                    <th class="num" style="width:120px"><?= e(t('total')) ?></th>
                    <th style="width:46px"></th>
                </tr>
                </thead>
                <tbody id="pos-lines">
                <tr id="pos-empty"><td colspan="5" class="empty"><?= e(t('pos_cart_empty')) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card pos-side">
        <div class="card-head"><h2><?= e(t('total')) ?></h2></div>
        <div class="card-body totals" style="gap:12px">
            <div class="row grand"><span><?= e(t('total')) ?></span><span id="pos-total">0,00</span></div>

            <div class="row">
                <span><?= e(t('paid')) ?></span>
                <input type="number" step="0.01" min="0" name="paid" id="pos-paid" value="0" style="width:130px;text-align:end">
            </div>
            <div class="row"><span><?= e(t('remaining')) ?></span><strong id="pos-remaining" style="color:var(--orange)">0,00</strong></div>

            <label class="field">
                <span><?= e(t('notes')) ?></span>
                <input type="text" name="note" value="<?= e(post('note')) ?>">
            </label>

            <button class="btn btn-primary btn-block" type="submit" id="pos-submit" style="margin-top:4px">
                <?= render_icon('print') ?><?= e(t('pos_submit')) ?>
            </button>
            <div class="small muted" style="text-align:center"><?= e(t('pos_print_hint')) ?></div>

            <a class="btn btn-ghost btn-block" href="<?= url('sales') ?>"><?= e(t('nav_sales')) ?></a>
        </div>
    </div>
</form>

<!-- Ajout rapide : client -->
<div class="modal" id="pos-client-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box" style="max-width:460px">
        <div class="modal-head">
            <h2><?= e(t('client_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" data-quick-form="client_quick" data-target="pos-client">
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

<script>
(function () {
    var products = <?= json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var byLabel = {};

    products.forEach(function (product) {
        byLabel[product.label.toLowerCase()] = product;
    });

    var form = document.getElementById('pos-form');
    var input = document.getElementById('pos-product');
    var qtyInput = document.getElementById('pos-qty');
    var priceInput = document.getElementById('pos-price');
    var lines = document.getElementById('pos-lines');
    var emptyRow = document.getElementById('pos-empty');
    var totalBox = document.getElementById('pos-total');
    var paidInput = document.getElementById('pos-paid');
    var remainingBox = document.getElementById('pos-remaining');
    var currency = <?= json_encode(CURRENCY) ?>;

    function format(value) {
        return value.toFixed(2).replace('.', ',') + ' ' + currency;
    }

    function refresh() {
        var total = 0;

        Array.prototype.forEach.call(lines.querySelectorAll('.pos-line'), function (row) {
            var qty = parseFloat(row.querySelector('.pos-qty').value || '0');
            var price = parseFloat(row.querySelector('.pos-price').value || '0');
            var lineTotal = Math.round(qty * price * 100) / 100;

            row.querySelector('.pos-line-total').textContent = format(lineTotal);
            total += lineTotal;
        });

        total = Math.round(total * 100) / 100;

        totalBox.textContent = format(total);

        if (paidInput.getAttribute('data-auto') !== '0') {
            paidInput.value = total.toFixed(2);
        }

        var paid = parseFloat(paidInput.value || '0');
        var remaining = Math.round((total - paid) * 100) / 100;

        remainingBox.textContent = format(Math.max(0, remaining));
        remainingBox.style.color = remaining > 0.009 ? 'var(--orange)' : 'var(--green)';

        if (emptyRow) {
            emptyRow.style.display = lines.querySelectorAll('.pos-line').length ? 'none' : '';
        }
    }

    function addLine() {
        var name = (input.value || '').trim();
        var qty = parseFloat(qtyInput.value || '0');
        var price = parseFloat(priceInput.value || '0');

        if (name === '' || qty <= 0) {
            input.focus();
            return;
        }

        var product = byLabel[name.toLowerCase()] || null;

        var row = document.createElement('tr');
        row.className = 'pos-line';
        row.innerHTML =
            '<td><div class="pos-line-name"></div>' +
            '<input type="hidden" name="name[]">' +
            '<input type="hidden" name="product_id[]">' +
            '<input type="hidden" name="qty[]" class="pos-qty">' +
            '<input type="hidden" name="price[]" class="pos-price"></td>' +
            '<td class="num pos-qty-view"></td>' +
            '<td class="num pos-price-view"></td>' +
            '<td class="num pos-line-total"></td>' +
            '<td><button class="btn btn-ghost btn-sm" type="button" data-pos-remove>×</button></td>';

        row.querySelector('.pos-line-name').textContent = name;
        row.querySelector('input[name="name[]"]').value = name;
        row.querySelector('input[name="product_id[]"]').value = product ? String(product.id) : '0';
        row.querySelector('input[name="qty[]"]').value = String(qty);
        row.querySelector('input[name="price[]"]').value = price.toFixed(2);
        row.querySelector('.pos-qty-view').textContent = String(qty).replace('.', ',');
        row.querySelector('.pos-price-view').textContent = price.toFixed(2).replace('.', ',');

        lines.appendChild(row);

        input.value = '';
        qtyInput.value = '1';
        priceInput.value = '0';
        input.focus();

        refresh();
    }

    document.getElementById('pos-add').addEventListener('click', addLine);

    input.addEventListener('change', function () {
        var product = byLabel[input.value.trim().toLowerCase()];

        if (product) {
            priceInput.value = product.price.toFixed(2);
            if (!qtyInput.value || parseFloat(qtyInput.value) <= 0) {
                qtyInput.value = '1';
            }
        }
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addLine();
        }
    });

    lines.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-pos-remove]');

        if (remove) {
            remove.closest('.pos-line').remove();
            refresh();
        }
    });

    paidInput.addEventListener('input', function () {
        paidInput.setAttribute('data-auto', '0');
        refresh();
    });

    form.addEventListener('submit', function (event) {
        if (! lines.querySelectorAll('.pos-line').length) {
            event.preventDefault();
            input.focus();
        }
    });

    refresh();
})();
</script>

<?php layout_end(); ?>
