<?php
declare(strict_types=1);

require_login();

$errors = [];
$savedId = 0;

$clientId = (int) (post('client_id') ?: get('client_id'));
$balance = $clientId > 0 ? client_balance($clientId) : 0.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $amount = post_float('amount');
    $date = (string) post('date', today());
    $method = in_array(post('method'), ['cash', 'card', 'transfer'], true) ? post('method') : 'cash';
    $note = trim((string) post('note'));

    $client = one('SELECT * FROM clients WHERE id = ? AND is_active = 1', [$clientId]);

    if (! $client) {
        $errors[] = t('error_required');
    } elseif ($amount <= 0) {
        $errors[] = t('error_required');
    } elseif ($amount > $balance + 0.009) {
        $errors[] = t('amount_exceeds');
    }

    if (! $errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            q(
                'INSERT INTO payments (client_id, user_id, date, amount, method, note) VALUES (?, ?, ?, ?, ?, ?)',
                [$clientId, (int) (user()['id'] ?? 0), $date, $amount, $method, $note]
            );

            $paymentId = (int) $pdo->lastInsertId();
            allocate_payment($paymentId, $clientId, $amount);

            $pdo->commit();
            $savedId = $paymentId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = $exception->getMessage();
        }

        if (! $errors && $savedId > 0) {
            flash(t('saved'));
            redirect('ticket', ['type' => 'payment', 'id' => $savedId, 'autoprint' => 1]);
        }
    }

    $balance = client_balance($clientId);
}

$clients = all('SELECT id, name, phone FROM clients WHERE is_active = 1 AND is_passenger = 0 ORDER BY name COLLATE NOCASE');
$previewClient = $clientId > 0 ? one('SELECT name FROM clients WHERE id = ?', [$clientId]) : null;

$openCredits = $clientId > 0
    ? all(
        "SELECT cr.*, s.date AS sale_date FROM credits cr JOIN sales s ON s.id = cr.sale_id
         WHERE cr.client_id = ? AND cr.status = 'open' ORDER BY cr.due_date, cr.id",
        [$clientId]
    )
    : [];

layout_start(t('payment_new'), 'payments');
print_header(t('payment_new'), $previewClient['name'] ?? '');
?>

<div class="toolbar">
    <a class="btn btn-ghost" href="<?= url('payments') ?>"><?= render_icon('back') ?><?= e(t('back')) ?></a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('payment_new')) ?></h2></div>
        <form class="card-body form" method="post" action="<?= url('payment_new') ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">

            <label class="field">
                <span><?= e(t('client')) ?> *</span>
                <select name="client_id" id="pay-client" required>
                    <option value="">— <?= e(t('client')) ?> —</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>"
                                data-balance="<?= e((string) client_balance((int) $client['id'])) ?>"
                                <?= $clientId === (int) $client['id'] ? 'selected' : '' ?>>
                            <?= e($client['name']) ?><?= $client['phone'] ? ' · ' . e($client['phone']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="alert alert-info" id="pay-balance">
                <?= e(t('balance')) ?> : <strong><?= money($balance) ?></strong>
            </div>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('amount')) ?> *</span>
                    <input type="number" step="0.01" min="0.01" name="amount" id="pay-amount" value="<?= e(post('amount')) ?>" required>
                </label>
                <label class="field">
                    <span><?= e(t('date')) ?></span>
                    <input type="date" name="date" value="<?= e(post('date') ?: today()) ?>" required>
                </label>
            </div>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('method')) ?></span>
                    <select name="method">
                        <option value="cash"><?= e(t('cash')) ?></option>
                        <option value="card"><?= e(t('card')) ?></option>
                        <option value="transfer"><?= e(t('transfer')) ?></option>
                    </select>
                </label>
                <label class="field">
                    <span><?= e(t('notes')) ?></span>
                    <input type="text" name="note" value="<?= e(post('note')) ?>">
                </label>
            </div>

            <button class="btn btn-primary btn-block" type="submit"><?= e(t('payment_submit')) ?></button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('client_credits')) ?></h2></div>
        <?php if (! $openCredits): ?>
            <div class="empty"><?= e(t('no_open_credit')) ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('date')) ?></th>
                        <th class="num"><?= e(t('remaining')) ?></th>
                        <th><?= e(t('due_date')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($openCredits as $credit): ?>
                        <tr>
                            <td><a href="<?= url('sale', ['id' => $credit['sale_id']]) ?>"><?= fr_date($credit['sale_date']) ?></a></td>
                            <td class="num" style="font-weight:700"><?= money($credit['remaining']) ?></td>
                            <td><?= fr_date($credit['due_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var select = document.getElementById('pay-client');
    var box = document.getElementById('pay-balance');
    var amount = document.getElementById('pay-amount');

    if (!select || !box) return;

    select.addEventListener('change', function () {
        var option = select.selectedOptions[0];
        var value = option ? parseFloat(option.getAttribute('data-balance') || '0') : 0;
        box.innerHTML = '<?= e(t('balance')) ?> : <strong>' + value.toFixed(2) + ' <?= CURRENCY ?></strong>';

        if (amount && !amount.value) {
            amount.value = value.toFixed(2);
        }
    });
})();
</script>

<?php layout_end(); ?>
