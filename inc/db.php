<?php
declare(strict_types=1);

/**
 * Connexion SQLite + création du schéma au premier lancement.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $fresh = ! file_exists(DB_FILE);

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    if ($fresh) {
        install_schema($pdo);
        seed_data($pdo);
    }

    return $pdo;
}

function install_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    address TEXT,
    credit_limit REAL NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    brand TEXT NOT NULL DEFAULT '',
    name TEXT NOT NULL,
    description TEXT,
    price REAL NOT NULL DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX idx_products_unique ON products (brand COLLATE NOCASE, name COLLATE NOCASE);

CREATE TABLE sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL REFERENCES clients(id),
    user_id INTEGER REFERENCES users(id),
    date TEXT NOT NULL,
    subtotal REAL NOT NULL DEFAULT 0,
    discount REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL DEFAULT 0,
    paid REAL NOT NULL DEFAULT 0,
    payment_method TEXT NOT NULL DEFAULT 'cash',
    status TEXT NOT NULL DEFAULT 'paid',
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE sale_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id),
    product_name TEXT NOT NULL,
    qty REAL NOT NULL DEFAULT 1,
    unit_price REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL DEFAULT 0
);

CREATE TABLE credits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL REFERENCES sales(id),
    client_id INTEGER NOT NULL REFERENCES clients(id),
    amount REAL NOT NULL,
    paid REAL NOT NULL DEFAULT 0,
    remaining REAL NOT NULL,
    due_date TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL REFERENCES clients(id),
    user_id INTEGER REFERENCES users(id),
    date TEXT NOT NULL,
    amount REAL NOT NULL,
    method TEXT NOT NULL DEFAULT 'cash',
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE payment_allocations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INTEGER NOT NULL REFERENCES payments(id) ON DELETE CASCADE,
    credit_id INTEGER NOT NULL REFERENCES credits(id),
    amount REAL NOT NULL
);

CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT
);

CREATE INDEX idx_sales_date ON sales(date);
CREATE INDEX idx_sales_client ON sales(client_id);
CREATE INDEX idx_credits_client ON credits(client_id);
CREATE INDEX idx_credits_status ON credits(status);
CREATE INDEX idx_payments_date ON payments(date);
CREATE INDEX idx_payments_client ON payments(client_id);
SQL);
}

function seed_data(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, username, password, role, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $stmt->execute(['Administrateur', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);

    $settings = [
        'shop_name' => 'Mon commerce',
        'shop_phone' => '',
        'shop_address' => '',
        'shop_footer' => 'Merci de votre visite',
        'credit_alert_days' => '30',
    ];

    $insert = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');

    foreach ($settings as $key => $value) {
        $insert->execute([$key, $value]);
    }
}

/* -------------------------------------------------------------------------
| Petits assistants de requêtes
|-------------------------------------------------------------------------*/

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();

    return $row === false ? null : $row;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = [])
{
    return q($sql, $params)->fetchColumn();
}

function setting(string $key, string $default = ''): string
{
    static $cache = [];

    if (! array_key_exists($key, $cache)) {
        $value = scalar('SELECT value FROM settings WHERE key = ?', [$key]);
        $cache[$key] = $value === false || $value === null ? $default : (string) $value;
    }

    return $cache[$key];
}
