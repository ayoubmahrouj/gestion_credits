<?php
declare(strict_types=1);

/**
 * Connexion MySQL (PHP natif, PDO).
 *
 * La base et les tables sont créées par le script SQL fourni
 * (database/schema_mysql.sql). Aucune création automatique ici.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
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
        $value = scalar('SELECT `value` FROM `settings` WHERE `key` = ?', [$key]);
        $cache[$key] = $value === false || $value === null ? $default : (string) $value;
    }

    return $cache[$key];
}

/**
 * Taille approximative de la base (octets), pour l'écran Paramètres.
 */
function database_size(): int
{
    try {
        return (int) scalar(
            'SELECT COALESCE(SUM(data_length + index_length), 0)
             FROM information_schema.tables WHERE table_schema = ?',
            [DB_NAME]
        );
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Sauvegarde SQL simple (structure + données) sans dépendre de mysqldump.
 */
function backup_database(string $path): bool
{
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $sql = "-- Sauvegarde " . DB_NAME . " - " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";

    foreach ($tables as $table) {
        $safe = str_replace('`', '``', (string) $table);
        $create = $pdo->query('SHOW CREATE TABLE `' . $safe . '`')->fetch(PDO::FETCH_NUM);
        $sql .= "\nDROP TABLE IF EXISTS `" . $safe . "`;\n" . $create[1] . ";\n";

        $rows = $pdo->query('SELECT * FROM `' . $safe . '`')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $cols = array_map(fn ($c) => '`' . str_replace('`', '``', (string) $c) . '`', array_keys($row));
            $vals = array_map(
                fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                array_values($row)
            );
            $sql .= 'INSERT INTO `' . $safe . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return file_put_contents($path, $sql) !== false;
}
