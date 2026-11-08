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

$name = trim((string) post('name'));

if ($name === '') {
    http_response_code(422);
    echo json_encode(['error' => t('error_required')]);
    exit;
}

$existing = one('SELECT * FROM brands WHERE lower(name) = lower(?)', [$name]);

if ($existing) {
    echo json_encode(['id' => (int) $existing['id'], 'name' => $existing['name']]);
    exit;
}

q('INSERT INTO brands (name) VALUES (?)', [$name]);

echo json_encode(['id' => (int) db()->lastInsertId(), 'name' => $name]);
