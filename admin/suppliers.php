<?php
/**
 * Управление поставщиками
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Поставщики';
$errors = [];

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = clean($_POST['name'] ?? '');
        $contactPerson = clean($_POST['contact_person'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $address = clean($_POST['address'] ?? '');
        $editId = (int)($_POST['id'] ?? 0);

        if (empty($name)) $errors[] = 'Введите название';
        if (!empty($email) && !isValidEmail($email)) $errors[] = 'Некорректный email';

        if (empty($errors)) {
            if ($action === 'add') {
                db()->query(
                    "INSERT INTO suppliers (name, contact_person, phone, email, address) 
                     VALUES (?, ?, ?, ?, ?)",
                    [$name, $contactPerson, $phone, $email, $address]
                );
                setFlash('success', 'Поставщик добавлен');
            } else {
                db()->query(
                    "UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?
                     WHERE id = ?",
                    [$name, $contactPerson, $phone, $email, $address, $editId]
                );
                setFlash('success', 'Поставщик обновлён');
            }
            redirect('/admin/suppliers.php');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $hasDeliveries = db()->fetchValue("SELECT COUNT(*) FROM deliveries WHERE supplier_id = ?", [$id]);
        if ($hasDeliveries) {
            setFlash('error', 'Нельзя удалить: есть связанные поставки');
        } else {
            db()->query("DELETE FROM suppliers WHERE id = ?", [$id]);
            setFlash('success', 'Поставщик удалён');
        }
        redirect('/admin/suppliers.php');
    }
}

$suppliers = db()->fetchAll(
    "SELECT s.*, 
            (SELECT COUNT(*) FROM deliveries WHERE supplier_id = s.id) AS deliveries_count,
            (SELECT COALESCE(SUM(cost), 0) FROM deliveries WHERE supplier_id = s.id) AS total_cost
     FROM suppliers s 
     ORDER BY s.name"
);

$editId = (int)($_GET['edit'] ?? 0);
$editSupplier = $editId ? db()->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$editId]) : null;

include __DIR__ . '/_header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="flash flash--error">
        <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
    <div>
        <h3 class="mb-2">Список поставщиков</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Контактное лицо</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Поставок</th>
                    <th>Сумма</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($suppliers)): ?>
                <tr><td colspan="8" class="text-center" style="padding: 30px;">Нет поставщиков</td></tr>
            <?php else: ?>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><strong><?= e($s['name']) ?></strong></td>
                        <td><?= e($s['contact_person'] ?: '—') ?></td>
                        <td><?= e($s['phone'] ?: '—') ?></td>
                        <td><?= e($s['email'] ?: '—') ?></td>
                        <td><?= $s['deliveries_count'] ?></td>
                        <td><?= formatPrice($s['total_cost']) ?></td>
                        <td class="admin-table__actions">
                            <a href="?edit=<?= $s['id'] ?>" class="btn btn--outline btn--small">✏️</a>
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn--outline btn--small" data-confirm="Удалить поставщика?">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div>
        <h3 class="mb-2"><?= $editSupplier ? 'Редактирование' : 'Добавить поставщика' ?></h3>
        <form method="POST" class="admin-form" data-validate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $editSupplier ? 'edit' : 'add' ?>">
            <?php if ($editSupplier): ?>
                <input type="hidden" name="id" value="<?= $editSupplier['id'] ?>">
            <?php endif; ?>

            <div class="form__group">
                <label class="form__label form__label--required">Название организации</label>
                <input type="text" name="name" class="form__input" 
                       value="<?= e($editSupplier['name'] ?? '') ?>" required>
            </div>

            <div class="form__group">
                <label class="form__label">Контактное лицо</label>
                <input type="text" name="contact_person" class="form__input" 
                       value="<?= e($editSupplier['contact_person'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label class="form__label">Телефон</label>
                <input type="tel" name="phone" class="form__input" 
                       value="<?= e($editSupplier['phone'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label class="form__label">Email</label>
                <input type="email" name="email" class="form__input" 
                       value="<?= e($editSupplier['email'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label class="form__label">Адрес</label>
                <textarea name="address" class="form__textarea"><?= e($editSupplier['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn--primary"><?= $editSupplier ? 'Сохранить' : 'Добавить' ?></button>
            <?php if ($editSupplier): ?>
                <a href="suppliers.php" class="btn btn--outline">Отмена</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
