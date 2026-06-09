<?php
/**
 * Управление промокодами
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Промокоды';
$errors = [];

// Действия
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $code = strtoupper(clean($_POST['code'] ?? ''));
        $discount = (int)($_POST['discount_percent'] ?? 0);
        $validUntil = clean($_POST['valid_until'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $editId = (int)($_POST['id'] ?? 0);

        if (empty($code)) $errors[] = 'Введите код промокода';
        if ($discount < 1 || $discount > 100) $errors[] = 'Скидка должна быть от 1 до 100%';

        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    db()->query(
                        "INSERT INTO promocodes (code, discount_percent, valid_until, is_active) 
                         VALUES (?, ?, ?, ?)",
                        [$code, $discount, $validUntil ?: null, $isActive]
                    );
                    setFlash('success', 'Промокод добавлен');
                } else {
                    db()->query(
                        "UPDATE promocodes SET code = ?, discount_percent = ?, valid_until = ?, is_active = ?
                         WHERE id = ?",
                        [$code, $discount, $validUntil ?: null, $isActive, $editId]
                    );
                    setFlash('success', 'Промокод обновлён');
                }
                redirect('/admin/promocodes.php');
            } catch (Exception $ex) {
                $errors[] = 'Промокод с таким кодом уже существует';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->query("DELETE FROM promocodes WHERE id = ?", [$id]);
        setFlash('success', 'Промокод удалён');
        redirect('/admin/promocodes.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->query("UPDATE promocodes SET is_active = NOT is_active WHERE id = ?", [$id]);
        redirect('/admin/promocodes.php');
    }
}

$promos = db()->fetchAll(
    "SELECT p.*, 
            (SELECT COUNT(*) FROM orders WHERE promocode_id = p.id) AS used_count,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE promocode_id = p.id) AS total_orders_amount
     FROM promocodes p 
     ORDER BY p.is_active DESC, p.id DESC"
);

$editId = (int)($_GET['edit'] ?? 0);
$editPromo = $editId ? db()->fetchOne("SELECT * FROM promocodes WHERE id = ?", [$editId]) : null;

include __DIR__ . '/_header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="flash flash--error">
        <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
    <div>
        <h3 class="mb-2">Все промокоды</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Код</th>
                    <th>Скидка</th>
                    <th>Действует до</th>
                    <th>Использований</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($promos)): ?>
                <tr><td colspan="6" class="text-center" style="padding: 30px;">Промокодов нет</td></tr>
            <?php else: ?>
                <?php foreach ($promos as $p): 
                    $isExpired = $p['valid_until'] && strtotime($p['valid_until']) < strtotime('today');
                ?>
                    <tr>
                        <td><strong style="font-family: monospace; font-size: 16px;"><?= e($p['code']) ?></strong></td>
                        <td><strong><?= $p['discount_percent'] ?>%</strong></td>
                        <td>
                            <?= $p['valid_until'] ? formatDate($p['valid_until']) : 'бессрочно' ?>
                            <?php if ($isExpired): ?>
                                <span style="color: #f44336; font-size: 12px;">(истёк)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['used_count'] ?></td>
                        <td>
                            <?php if ($p['is_active'] && !$isExpired): ?>
                                <span class="status-badge status-completed">Активен</span>
                            <?php else: ?>
                                <span class="status-badge status-cancelled">Не активен</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-table__actions">
                            <a href="?edit=<?= $p['id'] ?>" class="btn btn--outline btn--small">✏️</a>
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn--outline btn--small" 
                                        title="Переключить активность"><?= $p['is_active'] ? '⏸️' : '▶️' ?></button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn--outline btn--small" 
                                        data-confirm="Удалить промокод?">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div>
        <h3 class="mb-2"><?= $editPromo ? 'Редактирование' : 'Добавить промокод' ?></h3>
        <form method="POST" class="admin-form" data-validate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $editPromo ? 'edit' : 'add' ?>">
            <?php if ($editPromo): ?>
                <input type="hidden" name="id" value="<?= $editPromo['id'] ?>">
            <?php endif; ?>

            <div class="form__group">
                <label class="form__label form__label--required">Код</label>
                <input type="text" name="code" class="form__input" 
                       value="<?= e($editPromo['code'] ?? '') ?>" required 
                       style="font-family: monospace; text-transform: uppercase;"
                       placeholder="WELCOME10">
            </div>

            <div class="form__group">
                <label class="form__label form__label--required">Скидка, %</label>
                <input type="number" name="discount_percent" class="form__input" 
                       value="<?= e($editPromo['discount_percent'] ?? 10) ?>" 
                       min="1" max="100" required>
            </div>

            <div class="form__group">
                <label class="form__label">Действует до</label>
                <input type="date" name="valid_until" class="form__input" 
                       value="<?= e($editPromo['valid_until'] ?? '') ?>">
                <small class="text-muted">Если не задано — бессрочный</small>
            </div>

            <div class="form__group">
                <label>
                    <input type="checkbox" name="is_active" value="1" 
                           <?= ($editPromo['is_active'] ?? 1) ? 'checked' : '' ?>>
                    Активен
                </label>
            </div>

            <button type="submit" class="btn btn--primary"><?= $editPromo ? 'Сохранить' : 'Добавить' ?></button>
            <?php if ($editPromo): ?>
                <a href="promocodes.php" class="btn btn--outline">Отмена</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
