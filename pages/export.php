<?php
declare(strict_types=1);

require_login();
require __DIR__ . '/../inc/xlsx.php';

$type = (string) get('type', 'sales');
$from = (string) get('from', date('Y-m-01'));
$to = (string) get('to', today());
$clientId = (int) get('client_id');
$status = (string) get('status');
$method = (string) get('method');
$q = trim((string) get('q'));

$writer = new XlsxWriter();

/* -------------------------------------------------------------------------
| Ventes
|-------------------------------------------------------------------------*/

if (in_array($type, ['sales', 'report'], true)) {
    $where = 's.date BETWEEN ? AND ?';
    $params = [$from, $to];

    if ($clientId > 0) {
        $where .= ' AND s.client_id = ?';
        $params[] = $clientId;
    }

    if (in_array($status, ['paid', 'partial', 'credit'], true)) {
        $where .= ' AND s.status = ?';
        $params[] = $status;
    }

    if (in_array($method, ['cash', 'card', 'transfer', 'credit'], true)) {
        $where .= ' AND s.payment_method = ?';
        $params[] = $method;
    }

    $sales = all(
        "SELECT s.*, c.name AS client_name, c.phone AS client_phone
         FROM sales s JOIN clients c ON c.id = s.client_id
         WHERE {$where} ORDER BY s.date, s.id",
        $params
    );

    $saleRows = [];
    $total = 0.0;
    $paid = 0.0;

    foreach ($sales as $sale) {
        $saleRows[] = [
            (int) $sale['id'],
            fr_date($sale['date']),
            $sale['client_name'],
            $sale['client_phone'],
            (float) $sale['total'],
            (float) $sale['paid'],
            (float) $sale['total'] - (float) $sale['paid'],
            t($sale['payment_method']),
            sale_status_label($sale['status']),
        ];

        $total += (float) $sale['total'];
        $paid += (float) $sale['paid'];
    }

    $writer->addSheet(
        'Ventes',
        ['N°', 'Date', 'Client', 'Téléphone', 'Total', 'Payé', 'Reste', 'Mode', 'Statut'],
        $saleRows,
        [4, 5, 6]
    );

    $items = all(
        "SELECT si.*, s.date, c.name AS client_name FROM sale_items si
         JOIN sales s ON s.id = si.sale_id JOIN clients c ON c.id = s.client_id
         WHERE {$where} ORDER BY s.date, s.id, si.id",
        $params
    );

    $itemRows = [];
    foreach ($items as $item) {
        $itemRows[] = [
            (int) $item['sale_id'],
            fr_date($item['date']),
            $item['client_name'],
            $item['product_name'],
            (float) $item['qty'],
            (float) $item['unit_price'],
            (float) $item['total'],
        ];
    }

    $writer->addSheet(
        'Articles',
        ['N° vente', 'Date', 'Client', 'Produit', 'Qté', 'Prix unitaire', 'Total'],
        $itemRows,
        [5, 6]
    );

    if ($type === 'report') {
        $summary = [
            ['Période', fr_date($from) . ' → ' . fr_date($to)],
            ['Nombre de ventes', count($sales)],
            ['Chiffre d\'affaires', round($total, 2)],
            ['Encaissé sur ventes', round($paid, 2)],
            ['Vendu à crédit', round($total - $paid, 2)],
        ];

        $paymentsSum = (float) scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE date BETWEEN ? AND ?',
            [$from, $to]
        );
        $summary[] = ['Versements reçus', round($paymentsSum, 2)];

        $writer->addSheet('Synthèse', ['Indicateur', 'Valeur'], $summary);
    }
}

/* -------------------------------------------------------------------------
| Crédits
|-------------------------------------------------------------------------*/

if (in_array($type, ['credits', 'report'], true)) {
    $where = '1 = 1';
    $params = [];

    if ($status === 'open' || $status === 'paid') {
        $where .= ' AND cr.status = ?';
        $params[] = $status;
    }

    if ($clientId > 0) {
        $where .= ' AND cr.client_id = ?';
        $params[] = $clientId;
    }

    if ($type === 'credits' && $from !== '' && $to !== '') {
        $where .= ' AND date(cr.created_at) BETWEEN ? AND ?';
        $params[] = $from;
        $params[] = $to;
    }

    $credits = all(
        "SELECT cr.*, c.name AS client_name, c.phone AS client_phone, s.date AS sale_date
         FROM credits cr JOIN clients c ON c.id = cr.client_id JOIN sales s ON s.id = cr.sale_id
         WHERE {$where} ORDER BY cr.id",
        $params
    );

    $rows = [];
    $open = 0.0;
    foreach ($credits as $credit) {
        $rows[] = [
            (int) $credit['id'],
            fr_date($credit['sale_date']),
            $credit['client_name'],
            $credit['client_phone'],
            (float) $credit['amount'],
            (float) $credit['paid'],
            (float) $credit['remaining'],
            fr_date($credit['due_date']),
            credit_status_label($credit['status']),
        ];

        if ($credit['status'] === 'open') {
            $open += (float) $credit['remaining'];
        }
    }

    $writer->addSheet(
        'Crédits',
        ['N°', 'Date vente', 'Client', 'Téléphone', 'Montant', 'Payé', 'Reste', 'Échéance', 'Statut'],
        $rows,
        [4, 5, 6]
    );
}

/* -------------------------------------------------------------------------
| Versements
|-------------------------------------------------------------------------*/

if (in_array($type, ['payments', 'report'], true)) {
    $where = 'p.date BETWEEN ? AND ?';
    $params = [$from, $to];

    if ($clientId > 0) {
        $where .= ' AND p.client_id = ?';
        $params[] = $clientId;
    }

    if (in_array($method, ['cash', 'card', 'transfer'], true)) {
        $where .= ' AND p.method = ?';
        $params[] = $method;
    }

    $payments = all(
        "SELECT p.*, c.name AS client_name, c.phone AS client_phone
         FROM payments p JOIN clients c ON c.id = p.client_id
         WHERE {$where} ORDER BY p.date, p.id",
        $params
    );

    $rows = [];
    foreach ($payments as $payment) {
        $rows[] = [
            (int) $payment['id'],
            fr_date($payment['date']),
            $payment['client_name'],
            $payment['client_phone'],
            (float) $payment['amount'],
            t($payment['method']),
            $payment['note'],
        ];
    }

    $writer->addSheet(
        'Versements',
        ['N°', 'Date', 'Client', 'Téléphone', 'Montant', 'Mode', 'Remarque'],
        $rows,
        [4]
    );
}

/* -------------------------------------------------------------------------
| Clients
|-------------------------------------------------------------------------*/

if (in_array($type, ['clients', 'report'], true)) {
    $where = '1 = 1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    $clients = all(
        "SELECT c.*, COALESCE((SELECT SUM(remaining) FROM credits cr WHERE cr.client_id = c.id AND cr.status = 'open'), 0) AS balance
         FROM clients c WHERE {$where} ORDER BY c.name COLLATE NOCASE",
        $params
    );

    $rows = [];
    foreach ($clients as $client) {
        $rows[] = [
            (int) $client['id'],
            $client['name'],
            $client['phone'],
            $client['address'],
            (float) $client['credit_limit'],
            (float) $client['balance'],
            $client['is_active'] ? 'Actif' : 'Désactivé',
        ];
    }

    $writer->addSheet(
        'Clients',
        ['N°', 'Nom', 'Téléphone', 'Adresse', 'Plafond', 'Solde dû', 'Statut'],
        $rows,
        [4, 5]
    );
}

/* -------------------------------------------------------------------------
| Produits
|-------------------------------------------------------------------------*/

if (in_array($type, ['products', 'report'], true)) {
    $where = '1 = 1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (p.brand LIKE ? OR p.name LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    $products = all("SELECT p.* FROM products p WHERE {$where} ORDER BY p.brand COLLATE NOCASE, p.name COLLATE NOCASE", $params);

    $rows = [];
    foreach ($products as $product) {
        $rows[] = [
            (int) $product['id'],
            $product['brand'],
            $product['name'],
            $product['description'],
            (float) $product['price'],
            $product['is_active'] ? 'Actif' : 'Désactivé',
        ];
    }

    $writer->addSheet(
        'Produits',
        ['N°', 'Marque', 'Désignation', 'Description', 'Prix', 'Statut'],
        $rows,
        [4]
    );
}

/* -------------------------------------------------------------------------
| Envoi du fichier
|-------------------------------------------------------------------------*/

$filename = 'gestion-credits-' . preg_replace('/[^a-z]/', '', $type) . '-' . date('Ymd-Hi') . '.xlsx';
$path = EXPORT_DIR . DIRECTORY_SEPARATOR . $filename;

$writer->save($path);

if (! file_exists($path)) {
    http_response_code(500);
    exit('Export impossible.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: max-age=0');
header('Pragma: public');

readfile($path);
unlink($path);
exit;
