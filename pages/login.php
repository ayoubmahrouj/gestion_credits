<?php
declare(strict_types=1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $login = trim((string) post('login'));
    $password = (string) post('password');

    $found = one(
        'SELECT * FROM users WHERE username = ? OR lower(username) = lower(?)',
        [$login, $login]
    );

    if (! $found) {
        $error = t('login_error');
    } elseif (! $found['is_active']) {
        $error = t('login_disabled');
    } elseif (! password_verify($password, $found['password'])) {
        $error = t('login_error');
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $found['id'];
        redirect('pos');
    }
}

auth_layout_start(t('login_title'));
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark big">C</div>
            <h1><?= e(t('login_title')) ?></h1>
            <p class="muted"><?= e(t('login_subtitle')) ?></p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('login') ?>" class="form">
            <input type="hidden" name="_token" value="<?= csrf() ?>">

            <label class="field">
                <span><?= e(t('login_username')) ?></span>
                <input type="text" name="login" value="<?= e(post('login')) ?>" autocomplete="username" required autofocus placeholder="admin">
            </label>

            <label class="field">
                <span><?= e(t('login_password')) ?></span>
                <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
            </label>

            <button class="btn btn-primary btn-block" type="submit"><?= e(t('login_submit')) ?></button>
        </form>

        <div class="auth-switch">
            <a href="<?= url('login', ['lang' => locale() === 'fr' ? 'ar' : 'fr']) ?>">
                <?= locale() === 'fr' ? 'العربية' : 'Français' ?>
            </a>
        </div>
    </div>
</div>
<?php auth_layout_end(); ?>
