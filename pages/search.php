<?php
declare(strict_types=1);

require_login();

/* -------------------------------------------------------------------------
| Filtres
|-------------------------------------------------------------------------*/

$q = trim((string) get('q'));
$type = (string) get('type', 'all');
$from = (string) get('from');
$to = (string) get('to');
$status = (string) get('status');
$method = (string) get('method');

$minRaw = str_replace(',', '.', trim((string) get('min')));
$maxRaw = str_replace(',', '.', trim((string) get('max')));
$min = is_numeric($minRaw) ? (float) $minRaw : null;
$max = is_numeric($maxRaw) ? (float) $maxRaw : null;

$types = ['all', 'sales', 'clients', 'products', 'credits', 'payments'];

if (! in_array($type, $types, true)) {
    $type = 'all';
}

$like = '%' . $q . '%';
$limit = 200;

$saleStatus = in_array($status, ['paid', 'credit', 'partial'], true);
$creditStatus = in_array($status, ['open', 'paid'], true);
$clientStatus = in_array($status, ['active', 'inactive'], true);
$paymentMethod = in_array($method, ['cash', 'card', 'transfer'], true);

/* -------------------------------------------------------------------------
| Ventes
|-------------------------------------------------------------------------*/

$sales = [];
$salesCount = 0;

if ($q !== '' || $from !== '' || $to !== '' || $saleStatus || $method !== '' || $min !== null || $max !== null || $type === 'sales') {
    $where = '1 = 1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR s.note LIKE ? OR s.id = ? OR
                    EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND (si.product_name LIKE ? OR si.brand LIKE ?)))';
        array_push($params, $like, $like, $like, (int) $q, $like, $like);
    }

    if ($from !== '') {
        $where .= ' AND s.date >= ?';
        $params[] = $from;
    }

    if ($to !== '') {
        $where .= ' AND s.date <= ?';
        $params[] = $to;
    }

    if (in_array($status, ['paid', 'credit', 'partial'], true)) {
        $where .= ' AND s.status = ?';
        $params[] = $status;
    }

    if (in_array($method, ['cash', 'card', 'transfer', 'credit'], true)) {
        $where .= ' AND s.payment_method = ?';
        $params[] = $method;
    }

    if ($min !== null) {
        $where .= ' AND s.total >= ?';
        $params[] = $min;
    }

    if ($max !== null) {
        $where .= ' AND s.total <= ?';
        $params[] = $max;
    }

    $salesCount = (int) scalar("SELECT COUNT(*) FROM sales s JOIN clients c ON c.id = s.client_id WHERE {$where}", $params);
    $sales = all(
        "SELECT s.*, c.name AS client_name, c.phone AS client_phone
         FROM sales s JOIN clients c ON c.id = s.client_id
         WHERE {$where} ORDER BY s.date DESC, s.id DESC LIMIT {$limit}",
        $params
    );

    $saleIds = array_map(fn ($sale) => (int) $sale['id'], $sales);
    $itemsBySale = [];

    if ($saleIds) {
        $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
        foreach (all("SELECT sale_id, brand, product_name, qty FROM sale_items WHERE sale_id IN ({$placeholders}) ORDER BY id", $saleIds) as $row) {
            $itemsBySale[(int) $row['sale_id']][] = $row;
        }
    }
}

/* -------------------------------------------------------------------------
| Clients
|-------------------------------------------------------------------------*/

$clients = [];
$clientsCount = 0;

if ($q !== '' || $clientStatus || $type === 'clients') {
    $where = 'c.is_passenger = 0';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.address LIKE ? OR c.id = ?)';
        array_push($params, $like, $like, $like, (int) $q);
    }

    if ($status === 'active') {
        $where .= ' AND c.is_active = 1';
    } elseif ($status === 'inactive') {
        $where .= ' AND c.is_active = 0';
    }

    $clientsCount = (int) scalar("SELECT COUNT(*) FROM clients c WHERE {$where}", $params);
    $clients = all(
        "SELECT c.*, COALESCE((SELECT SUM(remaining) FROM credits cr WHERE cr.client_id = c.id AND cr.status = 'open'), 0) AS balance
         FROM clients c WHERE {$where} ORDER BY c.name LIMIT {$limit}",
        $params
    );
}

/* -------------------------------------------------------------------------
| Produits
|-------------------------------------------------------------------------*/

$products = [];
$productsCount = 0;

if ($q !== '' || $min !== null || $max !== null || $type === 'products') {
    $where = '1 = 1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ? OR p.id = ?)';
        array_push($params, $like, $like, $like, (int) $q);
    }

    if ($min !== null) {
        $where .= ' AND p.price >= ?';
        $params[] = $min;
    }

    if ($max !== null) {
        $where .= ' AND p.price <= ?';
        $params[] = $max;
    }

    $productsCount = (int) scalar("SELECT COUNT(*) FROM products p WHERE {$where}", $params);
    $products = all(
        "SELECT p.* FROM products p WHERE {$where} ORDER BY p.brand, p.name LIMIT {$limit}",
        $params
    );
}

/* -------------------------------------------------------------------------
| Crédits
|-------------------------------------------------------------------------*/

$credits = [];
$creditsCount = 0;

if ($q !== '' || $from !== '' || $to !== '' || $creditStatus || $min !== null || $max !== null || $type === 'credits') {
    $where = '1 = 1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR cr.id = ? OR cr.sale_id = ?)';
        array_push($params, $like, $like, (int) $q, (int) $q);
    }

    if (in_array($status, ['open', 'paid'], true)) {
        $where .= ' AND cr.status = ?';
        $params[] = $status;
    }

    if ($from !== '') {
        $where .= ' AND date(cr.created_at) >= ?';
        $params[] = $from;
    }

    if ($to !== '') {
        $where .= ' AND date(cr.created_at) <= ?';
        $params[] = $to;
    }

    if ($min !== null) {
        $where .= ' AND cr.remaining >= ?';
        $params[] = $min;
    }

    if ($max !== null) {
        $where .= ' AND cr.remaining <= ?';
        $params[] = $max;
    }

    $creditsCount = (int) scalar(
        "SELECT COUNT(*) FROM credits cr JOIN clients c ON c.id = cr.client_id WHERE {$where}",
        $params
    );
    $credits = all(
        "SELECT cr.*, c.name AS client_name, c.phone AS client_phone, s.date AS sale_date
         FROM credits cr
         JOIN clients c ON c.id = cr.client_id
         JOIN sales s ON s.id = cr.sale_id
         WHERE {$where}
         ORDER BY (cr.status = 'open') DESC, cr.due_date IS NULL, cr.due_date, cr.id DESC
         LIMIT {$limit}",
        $params
    );
}

/* -------------------------------------------------------------------------
| Versements
|-------------------------------------------------------------------------*/

$payments = [];
$paymentsCount = 0;

if ($q !== '' || $from !== '' || $to !== '' || $paymentMethod || $min !== null || $max !== null || $type === 'payments') {
    $where = '1 = 1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR p.note LIKE ? OR p.id = ?)';
        array_push($params, $like, $like, $like, (int) $q);
    }

    if ($from !== '') {
        $where .= ' AND p.date >= ?';
        $params[] = $from;
    }

    if ($to !== '') {
        $where .= ' AND p.date <= ?';
        $params[] = $to;
    }

    if (in_array($method, ['cash', 'card', 'transfer'], true)) {
        $where .= ' AND p.method = ?';
        $params[] = $method;
    }

    if ($min !== null) {
        $where .= ' AND p.amount >= ?';
        $params[] = $min;
    }

    if ($max !== null) {
        $where .= ' AND p.amount <= ?';
        $params[] = $max;
    }

    $paymentsCount = (int) scalar("SELECT COUNT(*) FROM payments p JOIN clients c ON c.id = p.client_id WHERE {$where}", $params);
    $payments = all(
        "SELECT p.*, c.name AS client_name, c.phone AS client_phone
         FROM payments p JOIN clients c ON c.id = p.client_id
         WHERE {$where} ORDER BY p.date DESC, p.id DESC LIMIT {$limit}",
        $params
    );
}

$showSales = $type === 'all' || $type === 'sales';
$showClients = $type === 'all' || $type === 'clients';
$showProducts = $type === 'all' || $type === 'products';
$showCredits = $type === 'all' || $type === 'credits';
$showPayments = $type === 'all' || $type === 'payments';

$totalResults = ($showSales ? $salesCount : 0)
    + ($showClients ? $clientsCount : 0)
    + ($showProducts ? $productsCount : 0)
    + ($showCredits ? $creditsCount : 0)
    + ($showPayments ? $paymentsCount : 0);

$hasFilters = $q !== '' || $from !== '' || $to !== '' || $status !== '' || $method !== ''
    || $min !== null || $max !== null || $type !== 'all';

layout_start(t('advanced_search'), 'search');
print_header(t('advanced_search'), $q !== '' ? $q : '');
?>

<div class="card">
    <div class="card-head">
        <h2><?= e(t('advanced_search')) ?></h2>
        <span class="muted small"><?= e(t('search_hint')) ?></span>
    </div>
    <form class="card-body filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="search">

        <label class="field" style="flex:1;min-width:220px">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('client') . ' / ' . t('product') . ' / #') ?>" autofocus>
        </label>

        <label class="field">
            <span><?= e(t('search_type')) ?></span>
            <select name="type">
                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>><?= e(t('search_all_types')) ?></option>
                <option value="sales" <?= $type === 'sales' ? 'selected' : '' ?>><?= e(t('search_sales')) ?></option>
                <option value="clients" <?= $type === 'clients' ? 'selected' : '' ?>><?= e(t('search_clients')) ?></option>
                <option value="products" <?= $type === 'products' ? 'selected' : '' ?>><?= e(t('search_products')) ?></option>
                <option value="credits" <?= $type === 'credits' ? 'selected' : '' ?>><?= e(t('search_credits')) ?></option>
                <option value="payments" <?= $type === 'payments' ? 'selected' : '' ?>><?= e(t('search_payments')) ?></option>
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

        <label class="field">
            <span><?= e(t('status')) ?></span>
            <select name="status">
                <option value=""><?= e(t('all')) ?></option>
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>><?= e(t('status_open')) ?></option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>><?= e(t('status_paid')) ?></option>
                <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>><?= e(t('status_partial')) ?></option>
                <option value="credit" <?= $status === 'credit' ? 'selected' : '' ?>><?= e(t('status_credit')) ?></option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e(t('active')) ?></option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>><?= e(t('inactive')) ?></option>
            </select>
        </label>

        <label class="field">
            <span><?= e(t('method')) ?></span>
            <select name="method">
                <option value=""><?= e(t('all')) ?></option>
                <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>><?= e(t('cash')) ?></option>
                <option value="card" <?= $method === 'card' ? 'selected' : '' ?>><?= e(t('card')) ?></option>
                <option value="transfer" <?= $method === 'transfer' ? 'selected' : '' ?>><?= e(t('transfer')) ?></option>
                <option value="credit" <?= $method === 'credit' ? 'selected' : '' ?>><?= e(t('credit')) ?></option>
            </select>
        </label>

        <label class="field">
            <span><?= e(t('min_amount')) ?></span>
            <input type="number" step="0.01" min="0" name="min" value="<?= e($minRaw) ?>">
        </label>

        <label class="field">
            <span><?= e(t('max_amount')) ?></span>
            <input type="number" step="0.01" min="0" name="max" value="<?= e($maxRaw) ?>">
        </label>

        <button class="btn btn-primary" type="submit"><?= e(t('search')) ?></button>
        <a class="btn btn-ghost" href="<?= url('search') ?>"><?= e(t('reset')) ?></a>
    </form>
</div>

<?php if (! $hasFilters): ?>
    <div class="empty"><?= e(t('search_empty')) ?></div>
<?php else: ?>
    <div class="toolbar">
        <span class="chip is-active"><?= number_format($totalResults, 0, ',', ' ') ?> <?= e(t('results')) ?></span>
        <div class="spacer"></div>
        <?php print_button(); ?>
    </div>

    <?php if ($totalResults === 0): ?>
        <div class="empty"><?= e(t('no_results')) ?></div>
    <?php endif; ?>

    <?php if ($showSales && $sales): ?>
        <div class="card">
            <div class="card-head">
                <h2><?= e(t('search_sales')) ?> <span class="muted small">(<?= $salesCount ?>)</span></h2>
                <?php pdf_button('sales', 0, ['from' => $from, 'to' => $to, 'status' => in_array($status, ['paid', 'partial', 'credit'], true) ? $status : '', 'method' => in_array($method, ['cash', 'card', 'transfer', 'credit'], true) ? $method : '', 'q' => $q]); ?>
                <a class="btn-link" href="<?= url('sales', array_filter(['from' => $from, 'to' => $to, 'status' => in_array($status, ['paid', 'partial', 'credit'], true) ? $status : '', 'method' => in_array($method, ['cash', 'card', 'transfer', 'credit'], true) ? $method : '', 'q' => $q])) ?>"><?= e(t('view_section')) ?></a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th><?= e(t('date')) ?></th>
                        <th><?= e(t('client')) ?></th>
                        <th><?= e(t('products')) ?></th>
                        <th class="num"><?= e(t('total')) ?></th>
                        <th class="num"><?= e(t('remaining')) ?></th>
                        <th><?= e(t('status')) ?></th>
                        <th class="actions"><?= e(t('actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td class="muted">#<?= (int) $sale['id'] ?></td>
                            <td><?= fr_date($sale['date']) ?></td>
                            <td>
                                <a href="<?= url('client', ['id' => $sale['client_id']]) ?>" style="font-weight:600"><?= e($sale['client_name']) ?></a>
                                <div class="small muted"><?= e($sale['client_phone'] ?: '') ?></div>
                            </td>
                            <td>
                                <?php foreach ($itemsBySale[(int) $sale['id']] ?? [] as $item): ?>
                                    <div class="small nowrap">
                                        <strong><?= e(item_label($item)) ?></strong>
                                        <span class="muted">× <?= e(rtrim(rtrim(number_format((float) $item['qty'], 2, ',', ' '), '0'), ',')) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td class="num"><?= money($sale['total']) ?></td>
                            <td class="num" style="font-weight:700"><?= money((float) $sale['total'] - (float) $sale['paid']) ?></td>
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
            <?php if ($salesCount > $limit): ?>
                <div class="empty small"><?= e(t('results_limited', ['limit' => $limit])) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($showClients && $clients): ?>
        <div class="card">
            <div class="card-head">
                <h2><?= e(t('search_clients')) ?> <span class="muted small">(<?= $clientsCount ?>)</span></h2>
                <a class="btn-link" href="<?= url('clients', ['q' => $q, 'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'all']) ?>"><?= e(t('view_section')) ?></a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th><?= e(t('name')) ?></th>
                        <th><?= e(t('phone')) ?></th>
                        <th><?= e(t('address')) ?></th>
                        <th class="num"><?= e(t('balance')) ?></th>
                        <th><?= e(t('status')) ?></th>
                        <th class="actions"><?= e(t('actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td class="muted">#<?= (int) $client['id'] ?></td>
                            <td><a href="<?= url('client', ['id' => $client['id']]) ?>" style="font-weight:600"><?= e($client['name']) ?></a></td>
                            <td><?= e($client['phone'] ?: '—') ?></td>
                            <td class="muted"><?= e($client['address'] ?: '—') ?></td>
                            <td class="num" style="font-weight:700;color:<?= (float) $client['balance'] > 0 ? 'var(--orange)' : 'var(--green)' ?>"><?= money($client['balance']) ?></td>
                            <td><span class="badge <?= $client['is_active'] ? 'badge-green' : 'badge-muted' ?>"><?= e($client['is_active'] ? t('active') : t('inactive')) ?></span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="<?= url('client', ['id' => $client['id']]) ?>"><?= e(t('client_detail')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($showProducts && $products): ?>
        <div class="card">
            <div class="card-head">
                <h2><?= e(t('search_products')) ?> <span class="muted small">(<?= $productsCount ?>)</span></h2>
                <a class="btn-link" href="<?= url('products', ['q' => $q, 'status' => 'all']) ?>"><?= e(t('view_section')) ?></a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('product')) ?></th>
                        <th><?= e(t('description')) ?></th>
                        <th class="num"><?= e(t('price')) ?></th>
                        <th class="num"><?= e(t('stock')) ?></th>
                        <th><?= e(t('status')) ?></th>
                        <th class="actions"><?= e(t('actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td style="font-weight:600"><?= e(product_label($product)) ?></td>
                            <td class="muted small"><?= e($product['description'] ?: '—') ?></td>
                            <td class="num"><?= money($product['price']) ?></td>
                            <td class="num"><?= (int) $product['stock'] ?></td>
                            <td><span class="badge <?= $product['is_active'] ? 'badge-green' : 'badge-muted' ?>"><?= e($product['is_active'] ? t('active') : t('inactive')) ?></span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="<?= url('products', ['edit' => $product['id']]) ?>"><?= e(t('edit')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($showCredits && $credits): ?>
        <div class="card">
            <div class="card-head">
                <h2><?= e(t('search_credits')) ?> <span class="muted small">(<?= $creditsCount ?>)</span></h2>
                <?php pdf_button('credits', 0, ['q' => $q, 'status' => in_array($status, ['open', 'paid'], true) ? $status : '', 'from' => $from, 'to' => $to]); ?>
                <a class="btn-link" href="<?= url('credits', array_filter(['q' => $q, 'status' => in_array($status, ['open', 'paid'], true) ? $status : '', 'from' => $from, 'to' => $to])) ?>"><?= e(t('view_section')) ?></a>
            </div>
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
                    <?php foreach ($credits as $credit): ?>
                        <tr>
                            <td class="muted">#<?= (int) $credit['id'] ?></td>
                            <td><?= fr_date($credit['sale_date']) ?></td>
                            <td><a href="<?= url('client', ['id' => $credit['client_id']]) ?>" style="font-weight:600"><?= e($credit['client_name']) ?></a><div class="small muted"><?= e($credit['client_phone'] ?: '') ?></div></td>
                            <td class="num"><?= money($credit['amount']) ?></td>
                            <td class="num"><?= money($credit['paid']) ?></td>
                            <td class="num" style="font-weight:700"><?= money($credit['remaining']) ?></td>
                            <td><?= fr_date($credit['due_date']) ?></td>
                            <td><span class="badge <?= $credit['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>"><?= e(credit_status_label($credit['status'])) ?></span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="<?= url('sale', ['id' => $credit['sale_id']]) ?>"><?= e(t('sale_detail')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($showPayments && $payments): ?>
        <div class="card">
            <div class="card-head">
                <h2><?= e(t('search_payments')) ?> <span class="muted small">(<?= $paymentsCount ?>)</span></h2>
                <?php pdf_button('payments', 0, ['q' => $q, 'from' => $from, 'to' => $to, 'method' => in_array($method, ['cash', 'card', 'transfer'], true) ? $method : '']); ?>
                <a class="btn-link" href="<?= url('payments', array_filter(['q' => $q, 'from' => $from, 'to' => $to, 'method' => in_array($method, ['cash', 'card', 'transfer'], true) ? $method : ''])) ?>"><?= e(t('view_section')) ?></a>
            </div>
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
                        <th class="actions"><?= e(t('actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="muted">#<?= (int) $payment['id'] ?></td>
                            <td><?= fr_date($payment['date']) ?></td>
                            <td><a href="<?= url('client', ['id' => $payment['client_id']]) ?>" style="font-weight:600"><?= e($payment['client_name']) ?></a><div class="small muted"><?= e($payment['client_phone'] ?: '') ?></div></td>
                            <td class="num" style="font-weight:700;color:var(--green)"><?= money($payment['amount']) ?></td>
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
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php layout_end(); ?>
