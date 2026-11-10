<?php
declare(strict_types=1);

require_login();

$errors = [];

/* -------------------------------------------------------------------------
| Téléchargement d'une sauvegarde
|-------------------------------------------------------------------------*/

if (get('download') !== '') {
    $file = basename((string) get('download'));

    if (preg_match('/^[A-Za-z0-9._-]+\.sql$/', $file) && is_file(BACKUP_DIR . DIRECTORY_SEPARATOR . $file)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize(BACKUP_DIR . DIRECTORY_SEPARATOR . $file));
        readfile(BACKUP_DIR . DIRECTORY_SEPARATOR . $file);
        exit;
    }

    flash(t('no_results'), 'error');
    redirect('settings');
}

/* -------------------------------------------------------------------------
| Actions
|-------------------------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'shop') {
        $values = [
            'shop_name' => trim((string) post('shop_name')),
            'shop_phone' => trim((string) post('shop_phone')),
            'shop_address' => trim((string) post('shop_address')),
            'shop_footer' => trim((string) post('shop_footer')),
            'credit_alert_days' => (string) max(1, post_int('credit_alert_days')),
        ];

        foreach ($values as $key => $value) {
            q(
                'INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                [$key, $value]
            );
        }

        flash(t('saved'));
        redirect('settings');
    }

    if ($action === 'password') {
        $current = (string) post('current_password');
        $new = (string) post('new_password');
        $confirm = (string) post('confirm_password');
        $me = user();

        if (! password_verify($current, $me['password'])) {
            $errors[] = t('wrong_password');
        } elseif (strlen($new) < 4) {
            $errors[] = t('error_required');
        } elseif ($new !== $confirm) {
            $errors[] = t('password_mismatch');
        }

        if (! $errors) {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            flash(t('password_updated'));
            redirect('settings');
        }
    }

    if ($action === 'backup') {
        $filename = 'gestion-' . date('Ymd-His') . '.sql';
        $path = BACKUP_DIR . DIRECTORY_SEPARATOR . $filename;

        if (backup_database($path)) {
            flash(t('backup_done'));

            // Création + téléchargement en un seul clic.
            redirect('settings', ['download' => $filename]);
        }

        flash(t('error_required'), 'error');
        redirect('settings');
    }

    if ($action === 'backup_delete') {
        $file = basename((string) post('file'));

        if (preg_match('/^[A-Za-z0-9._-]+\.sql$/', $file)) {
            @unlink(BACKUP_DIR . DIRECTORY_SEPARATOR . $file);
            flash(t('deleted'));
        }

        redirect('settings');
    }
}

$backups = [];
foreach (glob(BACKUP_DIR . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
    $backups[] = ['name' => basename($file), 'size' => filesize($file), 'time' => filemtime($file)];
}
usort($backups, fn ($a, $b) => $b['time'] <=> $a['time']);

$dbSize = database_size();

layout_start(t('settings'), 'settings');
print_header(t('settings'));
?>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2><?= e(t('shop_info')) ?></h2></div>
        <form class="card-body form" method="post" action="<?= url('settings') ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="shop">

            <label class="field">
                <span><?= e(t('shop_name')) ?></span>
                <input type="text" name="shop_name" value="<?= e(setting('shop_name')) ?>" required>
            </label>
            <div class="form-row">
                <label class="field">
                    <span><?= e(t('shop_phone')) ?></span>
                    <input type="text" name="shop_phone" value="<?= e(setting('shop_phone')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('credit_days')) ?></span>
                    <input type="number" min="1" name="credit_alert_days" value="<?= e(setting('credit_alert_days', '30')) ?>">
                </label>
            </div>
            <label class="field">
                <span><?= e(t('shop_address')) ?></span>
                <input type="text" name="shop_address" value="<?= e(setting('shop_address')) ?>">
            </label>
            <label class="field">
                <span><?= e(t('shop_footer')) ?></span>
                <input type="text" name="shop_footer" value="<?= e(setting('shop_footer')) ?>">
            </label>

            <div style="display:flex;justify-content:flex-end">
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2><?= e(t('account')) ?></h2></div>
        <form class="card-body form" method="post" action="<?= url('settings') ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="password">

            <div class="alert alert-info">
                <?= e(user()['name']) ?> · <?= e(t('role_' . (user()['role'] ?? 'admin'))) ?>
            </div>

            <label class="field">
                <span><?= e(t('current_password')) ?></span>
                <input type="password" name="current_password" required>
            </label>
            <div class="form-row">
                <label class="field">
                    <span><?= e(t('new_password')) ?></span>
                    <input type="password" name="new_password" required>
                </label>
                <label class="field">
                    <span><?= e(t('confirm_password')) ?></span>
                    <input type="password" name="confirm_password" required>
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end">
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card" id="backup">
    <div class="card-head">
        <h2><?= e(t('backup')) ?></h2>
        <span class="muted small"><?= number_format($dbSize / 1024, 1, ',', ' ') ?> Ko</span>
        <form method="post" action="<?= url('settings') ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="backup">
            <button class="btn btn-primary btn-sm" type="submit"><?= e(t('backup_download_create')) ?></button>
        </form>
    </div>
    <div class="card-body" style="padding-top:0;padding-bottom:0">
        <p class="muted small" style="margin-top:14px"><?= e(t('backup_hint')) ?></p>
    </div>

    <?php if (! $backups): ?>
        <div class="empty"><?= e(t('backup_none')) ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= e(t('date')) ?></th>
                    <th class="num">Taille</th>
                    <th class="actions"><?= e(t('actions')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td><?= fr_datetime(date('Y-m-d H:i:s', $backup['time'])) ?></td>
                        <td class="num"><?= number_format($backup['size'] / 1024, 1, ',', ' ') ?> Ko</td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="<?= url('settings', ['download' => $backup['name']]) ?>"><?= e(t('backup_download')) ?></a>
                            <form method="post" action="<?= url('settings') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= csrf() ?>">
                                <input type="hidden" name="action" value="backup_delete">
                                <input type="hidden" name="file" value="<?= e($backup['name']) ?>">
                                <button class="btn btn-danger btn-sm" type="submit" data-action="confirm" data-message="<?= e(t('confirm_disable')) ?>">
                                    <?= e(t('delete')) ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php layout_end(); ?>
