<?php
declare(strict_types=1);

require_login();
require_once __DIR__ . '/../inc/pdf.php';

/* -------------------------------------------------------------------------
| Petits formateurs
|-------------------------------------------------------------------------*/

function pdf_money($value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' ' . CURRENCY;
}

function pdf_qty($value): string
{
    return rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',');
}

function pdf_status_badge(string $status): string
{
    return '<span class="badge">' . e(sale_status_label($status)) . '</span>';
}

function pdf_table_start(array $columns): string
{
    $html = '<table><thead><tr>';

    foreach ($columns as $index => $column) {
        $class = is_array($column) ? ($column[1] ?? '') : '';
        $label = is_array($column) ? $column[0] : $column;
        $html .= '<th class="' . e($class) . '">' . e($label) . '</th>';
    }

    return $html . '</tr></thead><tbody>';
}

function pdf_table_end(string $rows, int $columns, string $empty = ''): string
{
    if ($rows === '') {
        return '<tr><td colspan="' . $columns . '" class="empty">' . e($empty !== '' ? $empty : t('no_results')) . '</td></tr></tbody></table>';
    }

    return $rows . '</tbody></table>';
}

function pdf_totals(array $lines, string $grandLabel, string $grandValue): string
{
    $html = '<div class="totals">';

    foreach ($lines as $label => $value) {
        $html .= '<div><span>' . e((string) $label) . '</span><strong>' . e((string) $value) . '</strong></div>';
    }

    return $html . '<div class="grand"><span>' . e($grandLabel) . '</span><strong>' . e($grandValue) . '</strong></div></div>';
}

/* -------------------------------------------------------------------------
| Documents
|-------------------------------------------------------------------------*/

function pdf_sale_document(int $id): array
{
    $sale = one(
        'SELECT s.*, c.name AS client_name, c.phone AS client_phone, c.address AS client_address,
                u.name AS user_name
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

    $rows = '';

    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td>' . e(item_label($item)) . '</td>'
            . '<td class="num">' . e(pdf_qty($item['qty'])) . '</td>'
            . '<td class="num">' . e(pdf_money($item['unit_price'])) . '</td>'
            . '<td class="num"><strong>' . e(pdf_money($item['total'])) . '</strong></td>'
            . '</tr>';
    }

    $remaining = (float) $sale['total'] - (float) $sale['paid'];

    $info = '<div class="section"><div class="info-grid">'
        . '<div><span>' . e(t('client')) . '</span><strong>' . e($sale['client_name']) . '</strong></div>'
        . '<div><span>' . e(t('phone')) . '</span><span>' . e($sale['client_phone'] ?: '—') . '</span></div>'
        . '<div><span>' . e(t('payment_method')) . '</span><span>' . e(t($sale['payment_method'])) . '</span></div>'
        . '<div><span>' . e(t('status')) . '</span><span>' . pdf_status_badge($sale['status']) . '</span></div>'
        . '<div><span>' . e(t('date')) . '</span><span>' . e(fr_date($sale['date'])) . '</span></div>'
        . '<div><span>' . e(t('nav_users')) . '</span><span>' . e($sale['user_name'] ?: '—') . '</span></div>'
        . '</div></div>';

    $table = '<div class="section"><h2>' . e(t('items')) . '</h2>';
    $table .= pdf_table_start([
        t('product'),
        [t('qty'), 'num'],
        [t('unit_price'), 'num'],
        [t('line_total'), 'num'],
    ]);
    $table .= pdf_table_end($rows, 4, t('no_items'));
    $table .= '</div>';

    $totals = pdf_totals([
        t('subtotal') => pdf_money($sale['subtotal']),
        t('discount') => '-' . pdf_money($sale['discount']),
        t('paid') => pdf_money($sale['paid']),
        t('remaining') => pdf_money($remaining),
    ], t('total'), pdf_money($sale['total']));

    $body = $info . $table . $totals;

    if ($credits) {
        $creditRows = '';

        foreach ($credits as $credit) {
            $creditRows .= '<tr>'
                . '<td class="num">' . e(pdf_money($credit['amount'])) . '</td>'
                . '<td class="num">' . e(pdf_money($credit['paid'])) . '</td>'
                . '<td class="num"><strong>' . e(pdf_money($credit['remaining'])) . '</strong></td>'
                . '<td>' . e(fr_date($credit['due_date'])) . '</td>'
                . '<td>' . e(credit_status_label($credit['status'])) . '</td>'
                . '</tr>';
        }

        $body .= '<div class="section"><h2>' . e(t('credits')) . '</h2>';
        $body .= pdf_table_start([
            [t('amount'), 'num'],
            [t('paid'), 'num'],
            [t('remaining'), 'num'],
            t('due_date'),
            t('status'),
        ]);
        $body .= pdf_table_end($creditRows, 5);
        $body .= '</div>';
    }

    $html = pdf_document(
        t('sale_detail') . ' #' . $id,
        fr_date($sale['date']) . ' · ' . setting('shop_name', APP_NAME),
        $body
    );

    return [$html, 'vente-' . $id . '.pdf'];
}

function pdf_payment_document(int $id): array
{
    $payment = one(
        'SELECT p.*, c.name AS client_name, c.phone AS client_phone, c.address AS client_address,
                u.name AS user_name
         FROM payments p
         JOIN clients c ON c.id = p.client_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?',
        [$id]
    );

    if (! $payment) {
        flash(t('no_results'), 'error');
        redirect('payments');
    }

    $balance = client_balance((int) $payment['client_id']);
    $allocations = all(
        'SELECT pa.amount, cr.id, cr.amount AS credit_amount, cr.remaining, s.id AS sale_id, s.date AS sale_date
         FROM payment_allocations pa
         JOIN credits cr ON cr.id = pa.credit_id
         JOIN sales s ON s.id = cr.sale_id
         WHERE pa.payment_id = ? ORDER BY pa.id',
        [$id]
    );

    $info = '<div class="section"><div class="info-grid">'
        . '<div><span>' . e(t('client')) . '</span><strong>' . e($payment['client_name']) . '</strong></div>'
        . '<div><span>' . e(t('phone')) . '</span><span>' . e($payment['client_phone'] ?: '—') . '</span></div>'
        . '<div><span>' . e(t('date')) . '</span><span>' . e(fr_date($payment['date'])) . '</span></div>'
        . '<div><span>' . e(t('method')) . '</span><span>' . e(t($payment['method'])) . '</span></div>'
        . '<div><span>' . e(t('amount')) . '</span><strong>' . e(pdf_money($payment['amount'])) . '</strong></div>'
        . '<div><span>' . e(t('balance')) . '</span><strong>' . e(pdf_money($balance)) . '</strong></div>'
        . '</div></div>';

    if ($payment['note']) {
        $info .= '<p class="muted small">' . e(t('notes')) . ' : ' . e($payment['note']) . '</p>';
    }

    $body = $info;

    if ($allocations) {
        $rows = '';

        foreach ($allocations as $allocation) {
            $rows .= '<tr>'
                . '<td>#' . (int) $allocation['sale_id'] . '</td>'
                . '<td>' . e(fr_date($allocation['sale_date'])) . '</td>'
                . '<td class="num">' . e(pdf_money($allocation['credit_amount'])) . '</td>'
                . '<td class="num">' . e(pdf_money($allocation['amount'])) . '</td>'
                . '</tr>';
        }

        $body .= '<div class="section"><h2>' . e(t('allocations')) . '</h2>';
        $body .= pdf_table_start([t('sale'), t('date'), [t('amount'), 'num'], [t('paid_now'), 'num']]);
        $body .= pdf_table_end($rows, 4);
        $body .= '</div>';
    }

    $body .= pdf_totals([], t('amount'), pdf_money($payment['amount']));

    $html = pdf_document(
        t('payment_receipt') . ' #' . $id,
        fr_date($payment['date']) . ' · ' . setting('shop_name', APP_NAME),
        $body
    );

    return [$html, 'versement-' . $id . '.pdf'];
}

function pdf_client_document(int $id): array
{
    $client = one('SELECT * FROM clients WHERE id = ?', [$id]);

    if (! $client) {
        flash(t('no_results'), 'error');
        redirect('clients');
    }

    $balance = client_balance($id);

    $sales = all('SELECT * FROM sales WHERE client_id = ? ORDER BY date DESC, id DESC', [$id]);
    $credits = all('SELECT * FROM credits WHERE client_id = ? ORDER BY created_at DESC, id DESC', [$id]);
    $payments = all('SELECT * FROM payments WHERE client_id = ? ORDER BY date DESC, id DESC', [$id]);

    $purchased = all(
        'SELECT si.*, s.date AS sale_date
         FROM sale_items si JOIN sales s ON s.id = si.sale_id
         WHERE s.client_id = ?
         ORDER BY s.date DESC, s.id DESC, si.id',
        [$id]
    );

    $itemsBySale = [];
    foreach ($purchased as $item) {
        $itemsBySale[(int) $item['sale_id']][] = $item;
    }

    $totalSales = (float) scalar('SELECT COALESCE(SUM(total), 0) FROM sales WHERE client_id = ?', [$id]);
    $totalPaid = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE client_id = ?', [$id]);

    $info = '<div class="kpis">'
        . '<div class="kpi"><span>' . e(t('balance')) . '</span><strong>' . e(pdf_money($balance)) . '</strong></div>'
        . '<div class="kpi"><span>' . e(t('sales')) . '</span><strong>' . e(pdf_money($totalSales)) . '</strong></div>'
        . '<div class="kpi"><span>' . e(t('payments')) . '</span><strong>' . e(pdf_money($totalPaid)) . '</strong></div>'
        . '<div class="kpi"><span>' . e(t('credit_limit')) . '</span><strong>' . e(pdf_money($client['credit_limit'])) . '</strong></div>'
        . '</div>';

    $info .= '<div class="section"><div class="info-grid">'
        . '<div><span>' . e(t('phone')) . '</span><span>' . e($client['phone'] ?: '—') . '</span></div>'
        . '<div><span>' . e(t('address')) . '</span><span>' . e($client['address'] ?: '—') . '</span></div>'
        . '<div><span>' . e(t('status')) . '</span><span>' . e($client['is_active'] ? t('active') : t('inactive')) . '</span></div>'
        . '<div><span>' . e(t('created_at')) . '</span><span>' . e(fr_date($client['created_at'] ?? '')) . '</span></div>'
        . '</div></div>';

    $body = $info;

    // Crédits
    if ($credits) {
        $rows = '';
        foreach ($credits as $credit) {
            $rows .= '<tr>'
                . '<td>#' . (int) $credit['sale_id'] . '</td>'
                . '<td>' . e(fr_date($credit['created_at'])) . '</td>'
                . '<td class="num">' . e(pdf_money($credit['amount'])) . '</td>'
                . '<td class="num">' . e(pdf_money($credit['paid'])) . '</td>'
                . '<td class="num"><strong>' . e(pdf_money($credit['remaining'])) . '</strong></td>'
                . '<td>' . e(fr_date($credit['due_date'])) . '</td>'
                . '<td>' . e(credit_status_label($credit['status'])) . '</td>'
                . '</tr>';
        }
        $body .= '<div class="section"><h2>' . e(t('client_credits')) . '</h2>';
        $body .= pdf_table_start([t('sale'), t('date'), [t('amount'), 'num'], [t('paid'), 'num'], [t('remaining'), 'num'], t('due_date'), t('status')]);
        $body .= pdf_table_end($rows, 7);
        $body .= '</div>';
    }

    // Ventes
    if ($sales) {
        $rows = '';
        foreach ($sales as $sale) {
            $products = [];
            foreach ($itemsBySale[(int) $sale['id']] ?? [] as $item) {
                $products[] = item_label($item) . ' × ' . pdf_qty($item['qty']);
            }
            $rows .= '<tr>'
                . '<td>#' . (int) $sale['id'] . '</td>'
                . '<td>' . e(fr_date($sale['date'])) . '</td>'
                . '<td class="small">' . e(implode(' · ', $products)) . '</td>'
                . '<td class="num">' . e(pdf_money($sale['total'])) . '</td>'
                . '<td class="num">' . e(pdf_money($sale['paid'])) . '</td>'
                . '<td>' . pdf_status_badge($sale['status']) . '</td>'
                . '</tr>';
        }
        $body .= '<div class="section"><h2>' . e(t('client_sales')) . '</h2>';
        $body .= pdf_table_start([t('sale'), t('date'), t('products'), [t('total'), 'num'], [t('paid'), 'num'], t('status')]);
        $body .= pdf_table_end($rows, 6);
        $body .= '</div>';
    }

    // Produits achetés
    if ($purchased) {
        $rows = '';
        foreach ($purchased as $item) {
            $rows .= '<tr>'
                . '<td>' . e(fr_date($item['sale_date'])) . '</td>'
                . '<td>' . e(item_label($item)) . '</td>'
                . '<td class="num">' . e(pdf_qty($item['qty'])) . '</td>'
                . '<td class="num">' . e(pdf_money($item['unit_price'])) . '</td>'
                . '<td class="num"><strong>' . e(pdf_money($item['total'])) . '</strong></td>'
                . '<td>#' . (int) $item['sale_id'] . '</td>'
                . '</tr>';
        }
        $body .= '<div class="section"><h2>' . e(t('client_products')) . '</h2>';
        $body .= pdf_table_start([t('date'), t('product'), [t('qty'), 'num'], [t('unit_price'), 'num'], [t('line_total'), 'num'], t('sale')]);
        $body .= pdf_table_end($rows, 6);
        $body .= '</div>';
    }

    // Versements
    if ($payments) {
        $rows = '';
        foreach ($payments as $payment) {
            $rows .= '<tr>'
                . '<td>' . e(fr_date($payment['date'])) . '</td>'
                . '<td class="num"><strong>' . e(pdf_money($payment['amount'])) . '</strong></td>'
                . '<td>' . e(t($payment['method'])) . '</td>'
                . '<td class="small">' . e($payment['note'] ?: '—') . '</td>'
                . '</tr>';
        }
        $body .= '<div class="section"><h2>' . e(t('client_payments')) . '</h2>';
        $body .= pdf_table_start([t('date'), [t('amount'), 'num'], t('method'), t('notes')]);
        $body .= pdf_table_end($rows, 4);
        $body .= '</div>';
    }

    $html = pdf_document(
        t('client_detail') . ' · ' . $client['name'],
        $client['phone'] ?: '',
        $body
    );

    return [$html, 'client-' . $id . '.pdf'];
}

function pdf_sales_document(): array
{
    $from = (string) get('from');
    $to = (string) get('to');
    $status = (string) get('status');
    $method = (string) get('method');
    $clientId = (int) get('client_id');
    $q = trim((string) get('q'));

    $sql = 'SELECT s.*, c.name AS client_name, c.phone AS client_phone
            FROM sales s JOIN clients c ON c.id = s.client_id
            WHERE 1 = 1';
    $params = [];

    if ($from !== '') {
        $sql .= ' AND s.date >= ?';
        $params[] = $from;
    }

    if ($to !== '') {
        $sql .= ' AND s.date <= ?';
        $params[] = $to;
    }

    if ($q !== '') {
        $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR s.id = ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = (int) $q;
    }

    if (in_array($status, ['paid', 'credit', 'partial'], true)) {
        $sql .= ' AND s.status = ?';
        $params[] = $status;
    }

    if (in_array($method, ['cash', 'card', 'transfer', 'credit'], true)) {
        $sql .= ' AND s.payment_method = ?';
        $params[] = $method;
    }

    if ($clientId > 0) {
        $sql .= ' AND s.client_id = ?';
        $params[] = $clientId;
    }

    $sql .= ' ORDER BY s.date, s.id';
    $sales = all($sql, $params);

    $rows = '';
    $total = 0.0;
    $paid = 0.0;

    foreach ($sales as $sale) {
        $remaining = (float) $sale['total'] - (float) $sale['paid'];
        $total += (float) $sale['total'];
        $paid += (float) $sale['paid'];

        $rows .= '<tr>'
            . '<td>#' . (int) $sale['id'] . '</td>'
            . '<td>' . e(fr_date($sale['date'])) . '</td>'
            . '<td>' . e($sale['client_name']) . '<div class="small muted">' . e($sale['client_phone'] ?: '') . '</div></td>'
            . '<td class="num">' . e(pdf_money($sale['total'])) . '</td>'
            . '<td class="num">' . e(pdf_money($sale['paid'])) . '</td>'
            . '<td class="num">' . e(pdf_money($remaining)) . '</td>'
            . '<td>' . e(t($sale['payment_method'])) . '</td>'
            . '<td>' . pdf_status_badge($sale['status']) . '</td>'
            . '</tr>';
    }

    $body = '<div class="section">';
    $body .= pdf_table_start([
        '#', t('date'), t('client'), [t('total'), 'num'], [t('paid'), 'num'], [t('remaining'), 'num'], t('payment_method'), t('status'),
    ]);
    $body .= pdf_table_end($rows, 8, t('no_sales'));
    $body .= '</div>';

    $body .= pdf_totals([
        t('kpi_paid') => pdf_money($paid),
        t('kpi_credit') => pdf_money($total - $paid),
    ], t('kpi_sales'), pdf_money($total));

    $period = $from === '' && $to === ''
        ? t('all')
        : (($from !== '' ? fr_date($from) : '…') . ' → ' . ($to !== '' ? fr_date($to) : '…'));

    $html = pdf_document(
        t('sales'),
        $period . ' · ' . count($sales) . ' ' . t('sales'),
        $body
    );

    return [$html, 'ventes-' . ($from !== '' || $to !== '' ? ($from ?: 'debut') . '-' . ($to ?: 'fin') : 'toutes') . '.pdf'];
}

function pdf_credits_document(): array
{
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

    $rows = '';
    $sumOpen = 0.0;
    $sumPaid = 0.0;

    foreach ($credits as $credit) {
        if ($credit['status'] === 'open') {
            $sumOpen += (float) $credit['remaining'];
        } else {
            $sumPaid += (float) $credit['amount'];
        }

        $rows .= '<tr>'
            . '<td>#' . (int) $credit['id'] . '</td>'
            . '<td>' . e(fr_date($credit['sale_date'])) . '</td>'
            . '<td>' . e($credit['client_name']) . '<div class="small muted">' . e($credit['client_phone'] ?: '') . '</div></td>'
            . '<td class="num">' . e(pdf_money($credit['amount'])) . '</td>'
            . '<td class="num">' . e(pdf_money($credit['paid'])) . '</td>'
            . '<td class="num"><strong>' . e(pdf_money($credit['remaining'])) . '</strong></td>'
            . '<td>' . e(fr_date($credit['due_date'])) . '</td>'
            . '<td>' . e(credit_status_label($credit['status'])) . '</td>'
            . '</tr>';
    }

    $body = '<div class="section">';
    $body .= pdf_table_start([
        '#', t('date'), t('client'), [t('amount'), 'num'], [t('paid'), 'num'], [t('remaining'), 'num'], t('due_date'), t('status'),
    ]);
    $body .= pdf_table_end($rows, 8, t('no_results'));
    $body .= '</div>';

    $body .= pdf_totals([
        t('status_paid') => pdf_money($sumPaid),
    ], t('outstanding'), pdf_money($sumOpen));

    $html = pdf_document(
        t('credits'),
        ($status === 'open' ? t('status_open') : ($status === 'paid' ? t('status_paid') : t('all'))) . ' · ' . count($credits),
        $body
    );

    return [$html, 'credits-' . date('Y-m-d') . '.pdf'];
}

function pdf_payments_document(): array
{
    $q = trim((string) get('q'));
    $from = (string) get('from');
    $to = (string) get('to');
    $method = (string) get('method');
    $clientId = (int) get('client_id');

    $sql = 'SELECT p.*, c.name AS client_name, c.phone AS client_phone
            FROM payments p JOIN clients c ON c.id = p.client_id
            WHERE 1 = 1';
    $params = [];

    if ($from !== '') {
        $sql .= ' AND p.date >= ?';
        $params[] = $from;
    }

    if ($to !== '') {
        $sql .= ' AND p.date <= ?';
        $params[] = $to;
    }

    if ($q !== '') {
        $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    if (in_array($method, ['cash', 'card', 'transfer'], true)) {
        $sql .= ' AND p.method = ?';
        $params[] = $method;
    }

    if ($clientId > 0) {
        $sql .= ' AND p.client_id = ?';
        $params[] = $clientId;
    }

    $sql .= ' ORDER BY p.date, p.id';
    $payments = all($sql, $params);

    $rows = '';
    $sum = 0.0;

    foreach ($payments as $payment) {
        $sum += (float) $payment['amount'];
        $rows .= '<tr>'
            . '<td>#' . (int) $payment['id'] . '</td>'
            . '<td>' . e(fr_date($payment['date'])) . '</td>'
            . '<td>' . e($payment['client_name']) . '<div class="small muted">' . e($payment['client_phone'] ?: '') . '</div></td>'
            . '<td class="num"><strong>' . e(pdf_money($payment['amount'])) . '</strong></td>'
            . '<td>' . e(t($payment['method'])) . '</td>'
            . '<td class="small">' . e($payment['note'] ?: '—') . '</td>'
            . '</tr>';
    }

    $body = '<div class="section">';
    $body .= pdf_table_start(['#', t('date'), t('client'), [t('amount'), 'num'], t('method'), t('notes')]);
    $body .= pdf_table_end($rows, 6, t('no_payments'));
    $body .= '</div>';

    $body .= pdf_totals([], t('kpi_payments'), pdf_money($sum));

    $period = $from === '' && $to === ''
        ? t('all')
        : (($from !== '' ? fr_date($from) : '…') . ' → ' . ($to !== '' ? fr_date($to) : '…'));

    $html = pdf_document(
        t('payments'),
        $period . ' · ' . count($payments),
        $body
    );

    return [$html, 'versements-' . ($from !== '' || $to !== '' ? ($from ?: 'debut') . '-' . ($to ?: 'fin') : 'tous') . '.pdf'];
}

/* -------------------------------------------------------------------------
| Routage
|-------------------------------------------------------------------------*/

$type = (string) get('type', 'sale');
$id = (int) get('id');

switch ($type) {
    case 'sale':
        if ($id <= 0) {
            flash(t('no_results'), 'error');
            redirect('sales');
        }
        [$html, $filename] = pdf_sale_document($id);
        break;

    case 'payment':
        if ($id <= 0) {
            flash(t('no_results'), 'error');
            redirect('payments');
        }
        [$html, $filename] = pdf_payment_document($id);
        break;

    case 'client':
        if ($id <= 0) {
            flash(t('no_results'), 'error');
            redirect('clients');
        }
        [$html, $filename] = pdf_client_document($id);
        break;

    case 'sales':
        [$html, $filename] = pdf_sales_document();
        break;

    case 'credits':
        [$html, $filename] = pdf_credits_document();
        break;

    case 'payments':
        [$html, $filename] = pdf_payments_document();
        break;

    default:
        flash(t('no_results'), 'error');
        redirect('dashboard');
}

pdf_stream($html, $filename, $type === 'client' ? 'clients' : 'dashboard');
