<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuration générale — Gestion Crédits (PHP natif)
|--------------------------------------------------------------------------
*/

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'data');
define('BACKUP_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'backups');
define('EXPORT_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'exports');

// MySQL (XAMPP par défaut : root sans mot de passe)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'gestion_credits_php');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Gestion Crédits');
define('CURRENCY', 'DH');
define('VERSION', '1.4');

date_default_timezone_set('Africa/Casablanca');

// Base URL : fonctionne dans n'importe quel sous-dossier (ex. /gestion-credits-php)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptDir = rtrim($scriptDir, '/');
define('BASE', ($scriptDir === '.' || $scriptDir === '/') ? '' : $scriptDir);

foreach ([DATA_DIR, BACKUP_DIR, EXPORT_DIR] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'path' => BASE . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Affichage des erreurs : activé en local, désactivable en production.
define('DEBUG', false);
ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);
