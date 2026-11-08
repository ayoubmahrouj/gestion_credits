<?php
declare(strict_types=1);

/* -------------------------------------------------------------------------
| Échappement, URLs, redirections
|-------------------------------------------------------------------------*/

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' ' . CURRENCY;
}

function url(string $page = 'dashboard', array $params = []): string
{
    $query = array_merge(['p' => $page], $params);

    return BASE . '/index.php?' . http_build_query($query);
}

function redirect(string $page = 'dashboard', array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function post(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = '')
{
    return $_GET[$key] ?? $default;
}

function post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function post_float(string $key): float
{
    return (float) str_replace(',', '.', (string) ($_POST[$key] ?? 0));
}

/* -------------------------------------------------------------------------
| Messages flash
|-------------------------------------------------------------------------*/

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $items;
}

/* -------------------------------------------------------------------------
| Jeton CSRF
|-------------------------------------------------------------------------*/

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';

    if ($expected === '' || ! is_string($token) || ! hash_equals($expected, $token)) {
        http_response_code(403);
        exit('Session expirée. Rechargez la page.');
    }
}

/* -------------------------------------------------------------------------
| Authentification
|-------------------------------------------------------------------------*/

function user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;

    if ($user === null) {
        $user = one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
    }

    return $user ?: null;
}

function is_admin(): bool
{
    return (user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (! user()) {
        redirect('login');
    }
}

function require_admin(): void
{
    require_login();

    if (! is_admin()) {
        flash(t('access_denied'), 'error');
        redirect('dashboard');
    }
}

/* -------------------------------------------------------------------------
| Langue (FR / AR)
|-------------------------------------------------------------------------*/

function locale(): string
{
    return $_SESSION['locale'] ?? 'fr';
}

function is_rtl(): bool
{
    return locale() === 'ar';
}

function t(string $key, array $replace = []): string
{
    static $lines = null;

    if ($lines === null) {
        $lines = require __DIR__ . '/lang.php';
    }

    $locale = locale();
    $text = $lines[$locale][$key] ?? $lines['fr'][$key] ?? $key;

    foreach ($replace as $search => $value) {
        $text = str_replace(':' . $search, (string) $value, $text);
    }

    return $text;
}

/* -------------------------------------------------------------------------
| Dates
|-------------------------------------------------------------------------*/

function today(): string
{
    return date('Y-m-d');
}

function fr_date(?string $date): string
{
    if (! $date) {
        return '—';
    }

    $ts = strtotime($date);

    return $ts ? date('d/m/Y', $ts) : $date;
}

function fr_datetime(?string $date): string
{
    if (! $date) {
        return '—';
    }

    $ts = strtotime($date);

    return $ts ? date('d/m/Y H:i', $ts) : $date;
}

/* -------------------------------------------------------------------------
| Métier : crédits
|-------------------------------------------------------------------------*/

function client_balance(int $clientId): float
{
    return (float) scalar(
        'SELECT COALESCE(SUM(remaining), 0) FROM credits WHERE client_id = ? AND status = ?',
        [$clientId, 'open']
    );
}

function client_total_due(int $clientId): float
{
    return client_balance($clientId);
}

/**
 * Impute un paiement sur les crédits ouverts du client (du plus ancien au plus récent).
 */
function allocate_payment(int $paymentId, int $clientId, float $amount): void
{
    $credits = all(
        'SELECT id, remaining FROM credits WHERE client_id = ? AND status = ? ORDER BY created_at, id',
        [$clientId, 'open']
    );

    $left = $amount;

    foreach ($credits as $credit) {
        if ($left <= 0.0001) {
            break;
        }

        $take = min($left, (float) $credit['remaining']);

        q(
            'INSERT INTO payment_allocations (payment_id, credit_id, amount) VALUES (?, ?, ?)',
            [$paymentId, $credit['id'], $take]
        );

        $newRemaining = round((float) $credit['remaining'] - $take, 2);

        q(
            'UPDATE credits SET paid = paid + ?, remaining = ?, status = ? WHERE id = ?',
            [$take, $newRemaining, $newRemaining <= 0.009 ? 'paid' : 'open', $credit['id']]
        );

        $left = round($left - $take, 2);
    }
}

function credit_status_label(string $status): string
{
    return $status === 'paid' ? t('status_paid') : t('status_open');
}

function sale_status_label(string $status): string
{
    return match ($status) {
        'paid' => t('status_paid'),
        'credit' => t('status_credit'),
        'partial' => t('status_partial'),
        default => $status,
    };
}
