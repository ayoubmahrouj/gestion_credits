<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Point d'entrée — Gestion Crédits (PHP natif)
|--------------------------------------------------------------------------
| Toutes les pages passent par ici : /index.php?p=clients
|--------------------------------------------------------------------------
*/

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/layout.php';

// Changement de langue (FR / AR)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'ar'], true)) {
    $_SESSION['locale'] = $_GET['lang'];
    $target = preg_replace('/[^a-z_]/', '', (string) ($_GET['p'] ?? 'pos')) ?: 'pos';
    unset($_GET['lang'], $_GET['p']);
    redirect($target, $_GET);
}

// Déconnexion
if (($_GET['p'] ?? '') === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE . '/index.php?p=login');
    exit;
}

$page = preg_replace('/[^a-z_]/', '', (string) ($_GET['p'] ?? 'pos'));
$page = $page !== '' ? $page : 'pos';

// Pages accessibles sans connexion
$publicPages = ['login'];

if (! user() && ! in_array($page, $publicPages, true)) {
    redirect('login');
}

if (user() && $page === 'login') {
    redirect('pos');
}

$file = __DIR__ . '/pages/' . $page . '.php';

if (! is_file($file)) {
    http_response_code(404);
    $page = 'dashboard';
    $file = __DIR__ . '/pages/dashboard.php';
}

require $file;
