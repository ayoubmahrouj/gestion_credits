<?php
declare(strict_types=1);

require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('payments');
}

verify_csrf();

$id = post_int('id');
$payment = one('SELECT * FROM payments WHERE id = ?', [$id]);

if ($payment) {
    reverse_payment_allocations($id);
    q('DELETE FROM payments WHERE id = ?', [$id]);
    flash(t('payment_deleted'));
}

redirect('payments');
