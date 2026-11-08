<?php
declare(strict_types=1);

require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = $_POST['_token'] ?? '';

if ($token === '' || ! is_string($token) || ! hash_equals($_SESSION['csrf'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => t('error_required')]);
    exit;
}

$brand = trim((string) post('brand'));
$name = trim((string) post('name'));
$description = trim((string) post('description'));
$price = post_float('price');

if ($name === '') {
    http_response_code(422);
    echo json_encode(['error' => t('error_required')]);
    exit;
}

if ($brand !== '') {
    q('INSERT OR IGNORE INTO brands (name) VALUES (?)', [$brand]);
}

$existing = one(
    'SELECT * FROM products WHERE lower(brand) = lower(?) AND lower(name) = lower(?)',
    [$brand, $name]
);

if ($existing) {
    if ($existing['is_active']) {
        http_response_code(409);
        echo json_encode(['error' => t('product_exists')]);
        exit;
    }

    q(
        'UPDATE products SET is_active = 1, price = ?, description = ? WHERE id = ?',
        [$price, $description, $existing['id']]
    );

    echo json_encode([
        'id' => (int) $existing['id'],
        'name' => ($brand !== '' ? $brand . ' — ' : '') . $name,
        'price' => $price,
    ]);
    exit;
}

q(
    'INSERT INTO products (brand, name, description, price) VALUES (?, ?, ?, ?)',
    [$brand, $name, $description, $price]
);

echo json_encode([
    'id' => (int) db()->lastInsertId(),
    'name' => ($brand !== '' ? $brand . ' — ' : '') . $name,
    'price' => $price,
]);
