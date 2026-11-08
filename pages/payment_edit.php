<?php
declare(strict_types=1);

require_login();
require_admin();

$id = (int) get('id');

$payment = one(
    'SELECT p.*, c.name AS client_name, c.phone AS client_phone
     FROM payments p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
    [$id]
);

if (! $payment) {
    flash(t('no_results'), 'error');
    redirect('payments');
}

$allocations = all(
    'SELECT pa.amount, cr.id AS credit_id, cr.amount AS credit_amount,
            s.id AS sale_id, s.date AS sale_date
     FROM payment_allocations pa
     JOIN credits cr ON cr.id = pa.credit_id
     JOIN sales s ON s.id = cr.sale_id
     WHERE pa.payment_id = ?
     ORDER BY pa.id',
    [$id]
);

$maxAmount = client_balance((int) $payment['client_id']) + (float) $payment['amount'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $amount = post_float('amount');
    $date = (string) (post('date') ?: today());
    $method = in_array(post('method'), ['cash', 'card', 'transfer'], true) ? post('method') : 'cash';
    $note = trim((string) post('note'));

    if ($amount <= 0) {
        $errors[] = t('error_required');
    } elseif ($amount > $maxAmount + 0.009) {
        $errors[] = t('amount_exceeds');
    }

    if (! $errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            reverse_payment_allocations($id);

            q(
                'UPDATE payments SET date = ?, amount = ?, method = ?, note = ? WHERE id = ?',
                [$date, $amount, $method, $note, $id]
            );

            allocate_payment($id, (int) $payment['client_id'], $amount);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = $exception->getMessage();
        }

        if (! $errors) {
            flash(t('payment_updated'));
            redirect('payments');
        }
    }

    $payment['date'] = $date;
    $payment['amount'] = $amount;
    $payment['method'] = $method;
    $payment['note'] = $note;
}

layout_start(t('payment_edit'), 'payments');
print_header(t('payment_edit') . ' #' . $id, $payment['client_name']);
?>

<div class="toolbar">
    <a class="btn btn-ghost" href="<?= url('payments') ?>"><?= render_icon('back') ?><?= e(t('back')) ?></a>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('ticket', ['type' => 'payment', 'id' => $id]) ?>" target="_blank"><?= e(t('receipt')) ?></a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('payment_edit')) ?> #<?= $id ?></h2></div>
        <form class="card-body form" method="post" action="<?= url('payment_edit', ['id' => $id]) ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">

            <label class="field">
                <span><?= e(t('client')) ?></span>
                <input type="text" value="<?= e($payment['client_name']) ?>" disabled>
            </label>

            <div class="alert alert-info">
                <?= e(t('max_amount')) ?> : <strong><?= money($maxAmount) ?></strong>
            </div>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('amount')) ?> *</span>
                    <input type="number" step="0.01" min="0.01" name="amount" value="<?= e((string) $payment['amount']) ?>" required>
                </label>
                <label class="field">
                    <span><?= e(t('date')) ?></span>
                    <input type="date" name="date" value="<?= e($payment['date']) ?>" required>
                </label>
            </div>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('method')) ?></span>
                    <select name="method">
                        <option value="cash" <?= $payment['method'] === 'cash' ? 'selected' : '' ?>><?= e(t('cash')) ?></option>
                        <option value="card" <?= $payment['method'] === 'card' ? 'selected' : '' ?>><?= e(t('card')) ?></option>
                        <option value="transfer" <?= $payment['method'] === 'transfer' ? 'selected' : '' ?>><?= e(t('transfer')) ?></option>
                    </select>
                </label>
                <label class="field">
                    <span><?= e(t('notes')) ?></span>
                    <input type="text" name="note" value="<?= e($payment['note'] ?? '') ?>">
                </label>
            </div>

            <button class="btn btn-primary btn-block" type="submit"><?= e(t('save')) ?></button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('allocations')) ?></h2></div>
        <?php if (! $allocations): ?>
            <div class="empty"><?= e(t('no_results')) ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('sale_detail')) ?></th>
                        <th><?= e(t('date')) ?></th>
                        <th class="num"><?= e(t('amount')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allocations as $allocation): ?>
                        <tr>
                            <td><a href="<?= url('sale', ['id' => (int) $allocation['sale_id']]) ?>">#<?= (int) $allocation['sale_id'] ?></a></td>
                            <td><?= fr_date($allocation['sale_date']) ?></td>
                            <td class="num"><?= money($allocation['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= url('payment_delete') ?>">
    <input type="hidden" name="_token" value="<?= csrf() ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn-danger" type="submit"
            data-action="confirm" data-message="<?= e(t('confirm_delete_payment')) ?>">
        <?= e(t('delete')) ?>
    </button>
</form>

<?php layout_end(); ?>
