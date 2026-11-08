<?php
declare(strict_types=1);

require_login();

$errors = [];

/* -------------------------------------------------------------------------
| Enregistrement
|-------------------------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = post_int('id');
        $brand = trim((string) post('brand'));
        $name = trim((string) post('name'));
        $description = trim((string) post('description'));
        $price = post_float('price');

        if ($name === '') {
            $errors[] = t('error_required');
        }

        // Unicité : Marque + Désignation (insensible à la casse)
        if (! $errors) {
            $existing = one(
                'SELECT * FROM products WHERE lower(brand) = lower(?) AND lower(name) = lower(?) AND id <> ?',
                [$brand, $name, $id]
            );

            if ($existing) {
                if (! $existing['is_active']) {
                    // Produit désactivé identique : on le réactive et on met à jour.
                    q(
                        'UPDATE products SET is_active = 1, description = ?, price = ? WHERE id = ?',
                        [$description, $price, $existing['id']]
                    );
                    flash(t('product_reactivated'));
                    redirect('products');
                }

                $errors[] = t('product_exists');
            }
        }

        if (! $errors) {
            if ($id > 0) {
                q(
                    'UPDATE products SET brand = ?, name = ?, description = ?, price = ? WHERE id = ?',
                    [$brand, $name, $description, $price, $id]
                );
            } else {
                q(
                    'INSERT INTO products (brand, name, description, price) VALUES (?, ?, ?, ?)',
                    [$brand, $name, $description, $price]
                );
            }

            // La marque utilisée est enregistrée dans la liste des marques.
            if ($brand !== '') {
                q('INSERT OR IGNORE INTO brands (name) VALUES (?)', [$brand]);
            }

            flash(t('saved'));
            redirect('products');
        }
    }

    if ($action === 'toggle') {
        $id = post_int('id');
        $current = one('SELECT is_active FROM products WHERE id = ?', [$id]);

        if ($current) {
            q('UPDATE products SET is_active = ? WHERE id = ?', [$current['is_active'] ? 0 : 1, $id]);
            flash($current['is_active'] ? t('deleted') : t('restored'));
        }

        redirect('products');
    }
}

/* -------------------------------------------------------------------------
| Filtres
|-------------------------------------------------------------------------*/

$q = trim((string) get('q'));
$status = (string) get('status', 'active');

$sql = 'SELECT * FROM products WHERE 1 = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR brand LIKE ? OR description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($status === 'active') {
    $sql .= ' AND is_active = 1';
} elseif ($status === 'inactive') {
    $sql .= ' AND is_active = 0';
}

$sql .= ' ORDER BY brand COLLATE NOCASE, name COLLATE NOCASE';
$products = all($sql, $params);

// Liste des marques : table brands + marques déjà présentes sur des produits.
$brands = array_column(
    all(
        "SELECT DISTINCT name FROM (
            SELECT name FROM brands WHERE name <> ''
            UNION
            SELECT brand AS name FROM products WHERE brand <> ''
         ) ORDER BY name COLLATE NOCASE"
    ),
    'name'
);

$editing = null;
if ($editId = (int) get('edit')) {
    $editing = one('SELECT * FROM products WHERE id = ?', [$editId]);
}

$showModal = $editing !== null || get('new') === '1' || $errors;

$currentBrand = (string) ($editing['brand'] ?? post('brand'));

layout_start(t('products'), 'products');
print_header(t('products'));
?>

<div class="toolbar">
    <form class="filters" method="get" action="<?= BASE ?>/index.php">
        <input type="hidden" name="p" value="products">
        <label class="field">
            <span><?= e(t('search')) ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('brand') . ' / ' . t('designation')) ?>">
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
        <a class="btn btn-ghost" href="<?= url('products') ?>"><?= e(t('reset')) ?></a>
    </form>
    <div class="spacer"></div>
    <?php print_button(); ?>
    <button class="btn btn-primary" type="button" data-action="modal-open" data-target="product-modal">+ <?= e(t('product_new')) ?></button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th><?= e(t('brand')) ?></th>
                <th><?= e(t('designation')) ?></th>
                <th class="num"><?= e(t('price')) ?></th>
                <th><?= e(t('status')) ?></th>
                <th class="actions"><?= e(t('actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (! $products): ?>
                <tr><td colspan="5" class="empty"><?= e(t('no_results')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td style="font-weight:600" class="nowrap"><?= e($product['brand'] ?: '—') ?></td>
                    <td>
                        <?= e($product['name']) ?>
                        <?php if ($product['description']): ?><div class="small muted"><?= e($product['description']) ?></div><?php endif; ?>
                    </td>
                    <td class="num"><?= money($product['price']) ?></td>
                    <td>
                        <?php if ($product['is_active']): ?>
                            <span class="badge badge-green"><?= e(t('active')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-muted"><?= e(t('inactive')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('products', ['edit' => $product['id']]) ?>"><?= e(t('edit')) ?></a>
                        <form method="post" action="<?= url('products') ?>" style="display:inline">
                            <input type="hidden" name="_token" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button class="btn btn-sm <?= $product['is_active'] ? 'btn-danger' : 'btn-ghost' ?>" type="submit"
                                    data-action="confirm" data-message="<?= e(t('confirm_disable')) ?>">
                                <?= e($product['is_active'] ? t('disable') : t('enable')) ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modale produit -->
<div class="modal <?= $showModal ? 'is-open' : '' ?>" id="product-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box">
        <div class="modal-head">
            <h2><?= e($editing ? t('product_edit') : t('product_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" method="post" action="<?= url('products') ?>">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <div class="form-row">
                <label class="field">
                    <span><?= e(t('brand')) ?></span>
                    <div class="quick-field">
                        <select name="brand" id="product-brand">
                            <option value="">— <?= e(t('brand')) ?> —</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= e($brand) ?>" <?= $currentBrand === $brand ? 'selected' : '' ?>><?= e($brand) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="quick-add" type="button" data-action="modal-open" data-target="quick-brand-modal"
                                title="<?= e(t('brand_new')) ?>">+</button>
                    </div>
                </label>
                <label class="field">
                    <span><?= e(t('designation')) ?> *</span>
                    <input type="text" name="name" value="<?= e($editing['name'] ?? post('name')) ?>" required placeholder="FILTRE A HUILE">
                </label>
            </div>

            <label class="field">
                <span><?= e(t('description')) ?></span>
                <input type="text" name="description" value="<?= e($editing['description'] ?? post('description')) ?>">
            </label>

            <label class="field">
                <span><?= e(t('price')) ?> *</span>
                <input type="number" step="0.01" min="0" name="price" value="<?= e($editing['price'] ?? post('price', '0')) ?>" required>
            </label>

            <div class="alert alert-info small"><?= e(t('product_exists')) ?></div>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button class="btn btn-ghost" type="button" data-action="modal-close"><?= e(t('cancel')) ?></button>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Ajout rapide : marque -->
<div class="modal" id="quick-brand-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-box" style="max-width:420px">
        <div class="modal-head">
            <h2><?= e(t('brand_new')) ?></h2>
            <button class="btn btn-ghost btn-sm" type="button" data-action="modal-close"><?= e(t('close')) ?></button>
        </div>
        <form class="modal-body form" data-quick-form="brand_quick" data-target="product-brand" data-value="name">
            <input type="hidden" name="_token" value="<?= csrf() ?>">
            <label class="field">
                <span><?= e(t('brand')) ?> *</span>
                <input type="text" name="name" required placeholder="DUSTER" autocomplete="off">
            </label>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button class="btn btn-ghost" type="button" data-action="modal-close"><?= e(t('cancel')) ?></button>
                <button class="btn btn-primary" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php layout_end(); ?>
