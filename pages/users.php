<?php
declare(strict_types=1);

require_admin();

$errors = [];
$roles = ['admin', 'manager', 'cashier', 'accountant'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = post_int('id');
        $name = trim((string) post('name'));
        $username = trim((string) post('username'));
        $role = in_array(post('role'), $roles, true) ? post('role') : 'cashier';
        $password = (string) post('password');

        if ($name === '' || $username === '') {
            $errors[] = t('error_required');
        }

        if (! $errors) {
            $taken = one('SELECT id FROM users WHERE lower(username) = lower(?) AND id <> ?', [$username, $id]);

            if ($taken) {
                $errors[] = t('username_taken');
            }
        }

        if (! $errors && $id === 0 && strlen($password) < 4) {
            $errors[] = t('error_required');
        }

        if (! $errors) {
            if ($id > 0) {
                q('UPDATE users SET name = ?, username = ?, role = ? WHERE id = ?', [$name, $username, $role, $id]);

                if ($password !== '') {
                    q('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            } else {
                q(
                    'INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)',
                    [$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]
                );
            }

            flash(t('saved'));
            redirect('users');
        }
    }

    if ($action === 'toggle') {
        $id = post_int('id');
        $me = user();

        if ($id === (int) $me['id']) {
            flash(t('cannot_disable_self'), 'error');
            redirect('users');
        }

        $current = one('SELECT is_active FROM users WHERE id = ?', [$id]);

        if ($current) {
            q('UPDATE users SET is_active = ? WHERE id = ?', [$current['is_active'] ? 0 : 1, $id]);
            flash($current['is_active'] ? t('deleted') : t('restored'));
        }

        redirect('users');
    }
}

/* ------------------------------- Filtres ------------------------------- */

$q = trim((string) get('q'));
$role = (string) get('role');
$status = (string) get('status', 'active');

$sql = 'SELECT * FROM users WHERE 1 = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR username LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if (in_array($role, $roles, true)) {
    $sql .= ' AND role = ?';
    $params[] = $role;
}

if ($status === 'active') {
    $sql .= ' AND is_active = 1';
} elseif ($status === 'inactive') {
    $sql .= ' AND is_active = 0';
}

$sql .= ' ORDER BY name';
$users = all($sql, $params);

$editing = null;
if ($editId = (int) get('edit')) {
    $editing = one('SELECT * FROM users WHERE id = ?', [$editId]);
}

$showModal = $editing !== null || get('new') === '1' || $errors;

layout_start(t('users'), 'users');
print_header(t('users'));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="users">
        <label class="field">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($q) ?>">
        </label>
        <label class="field">
            <span><?= e(t('role')) ?></span>
            <select name="role">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(t('role_' . $r)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span><?= e(t('status')) ?></span>
            <select name="status">
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e(t('active')) ?></option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>><?= e(t('inactive')) ?></option>
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>><?= e(t('all')) ?></option>
            </select>
        </label>
        <button class="btn btn-ghost" type="submit"><?= e(t('filter')) ?></button>
        <a class="btn btn-ghost" href="<?= url('users') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <button class="btn btn-primary" type="button" data-action="modal-open" data-target="user-modal">+ <?= e(t('user_new')) ?></button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e(t('name')) ?></th>
                <th><?= e(t('username')) ?></th>
                <th><?= e(t('role')) ?></th>
                <th><?= e(t('status')) ?></th>
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $users): ?>
                <tr><td colspan="5" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td style="font-weight:600"><?= e($row['name']) ?></td>
                    <td><?= e($row['username']) ?></td>
                    <td><?= e(t('role_' . $row['role'])) ?></td>
                    <td>
                        <span class="badge <?= $row['is_active'] ? 'badge-green' : 'badge-muted' ?>">
                            <?= e($row['is_active'] ? t('active') : t('inactive')) ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('users', ['edit' => $row['id']]) ?>"><?= e(t('edit')) ?></a>
                        <?php if ((int) $row['id'] !== (int) user()['id']): ?>
                            <form method="post" action="<?= url('users') ?>" style="display:inline">
                                <input type="hidden" name="_token" value="<?= csrf() ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-sm <?= $row['is_active'] ? 'btn-danger' : 'btn-ghost' ?>" type="submit"
                                        data-action="confirm" data-message="<?= e(t('confirm_disable')) ?>">
                                    <?= e($row['is_active'] ? t('disable') : t('enable')) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal <?= $showModal ? 'is-open' : '' ?>" id="user-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box">
        <div class="modal-head">
            <h2><?= e($editing ? t('user_edit') : t('user_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" method="post" action="<?= url('users') ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('name')) ?> *</span>
                    <input type="text" name="name" value="<?= e($editing['name'] ?? post('name')) ?>" required>
                </label>
                <label class="field">
                    <span><?= e(t('username')) ?> *</span>
                    <input type="text" name="username" value="<?= e($editing['username'] ?? post('username')) ?>" required autocomplete="off">
                </label>
            </div>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('role')) ?></span>
                    <select name="role">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>" <?= ($editing['role'] ?? 'cashier') === $r ? 'selected' : '' ?>><?= e(t('role_' . $r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span><?= e($editing ? t('reset_password') : t('password')) ?><?= $editing ? '' : ' *' ?></span>
                    <input type="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button class="btn btn-ghost" type="button" data-action="modal-close"><?= e(t('cancel')) ?></button>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php layout_end(); ?>
