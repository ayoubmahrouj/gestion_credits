<?php
declare(strict_types=1);

require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('sales');
}

verify_csrf();

$id = post_int('id');
$sale = one('SELECT * FROM sales WHERE id = ?', [$id]);

if ($sale) {
    $credit = one('SELECT * FROM credits WHERE sale_id = ?', [$id]);

    if ($credit) {
        q('DELETE FROM payment_allocations WHERE credit_id = ?', [(int) $credit['id']]);
        q('DELETE FROM credits WHERE id = ?', [(int) $credit['id']]);
    }

    q('DELETE FROM sale_items WHERE sale_id = ?', [$id]);
    q('DELETE FROM sales WHERE id = ?', [$id]);

    flash(t('sale_deleted'));
}

redirect('sales');
