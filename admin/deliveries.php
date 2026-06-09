<?php
/**
 * Учёт поставок (приход товара)
 * При добавлении поставки автоматически увеличивается остаток на складе
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Поставки';
$errors = [];

// Добавление поставки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $deliveryDate = clean($_POST['delivery_date'] ?? '');
        $cost = (float)($_POST['cost'] ?? 0);

        if (!$supplierId) $errors[] = 'Выберите поставщика';
        if (!$productId) $errors[] = 'Выберите товар';
        if ($quantity <= 0) $errors[] = 'Количество должно быть больше 0';
        if (empty($deliveryDate)) $errors[] = 'Укажите дату поставки';
        if ($cost < 0) $errors[] = 'Стоимость не может быть отрицательной';

        if (empty($errors)) {
            try {
                db()->beginTransaction();

                // Добавляем поставку
                db()->query(
                    "INSERT INTO deliveries (supplier_id, product_id, quantity, delivery_date, cost) 
                     VALUES (?, ?, ?, ?, ?)",
                    [$supplierId, $productId, $quantity, $deliveryDate, $cost]
                );

                // АВТООБНОВЛЕНИЕ остатков на складе
                db()->query(
                    "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                    [$quantity, $productId]
                );

                db()->commit();
                logMessage("Принята поставка: товар #$productId, кол-во $quantity", 'INFO');
                setFlash('success', "Поставка принята. Остаток товара увеличен на $quantity шт.");
            } catch (Exception $ex) {
                db()->rollBack();
                $errors[] = 'Ошибка: ' . $ex->getMessage();
            }
            if (empty($errors)) {
                redirect('/admin/deliveries.php');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // При удалении поставки уменьшаем остатки обратно
        $delivery = db()->fetchOne("SELECT * FROM deliveries WHERE id = ?", [$id]);
        if ($delivery) {
            try {
                db()->beginTransaction();
                db()->query(
                    "UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?",
                    [$delivery['quantity'], $delivery['product_id']]
                );
                db()->query("DELETE FROM deliveries WHERE id = ?", [$id]);
                db()->commit();
                setFlash('success', 'Поставка удалена');
            } catch (Exception $ex) {
                db()->rollBack();
                setFlash('error', 'Ошибка при удалении');
            }
        }
        redirect('/admin/deliveries.php');
    }
}

// Фильтры
$supplierFilter = (int)($_GET['supplier'] ?? 0);
$dateFrom = clean($_GET['date_from'] ?? '');
$dateTo = clean($_GET['date_to'] ?? '');

$where = ['1=1'];
$params = [];

if ($supplierFilter) {
    $where[] = "d.supplier_id = ?";
    $params[] = $supplierFilter;
}
if ($dateFrom) {
    $where[] = "d.delivery_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "d.delivery_date <= ?";
    $params[] = $dateTo;
}

$deliveries = db()->fetchAll(
    "SELECT d.*, s.name AS supplier_name, p.name AS product_name 
     FROM deliveries d 
     LEFT JOIN suppliers s ON d.supplier_id = s.id 
     LEFT JOIN products p ON d.product_id = p.id 
     WHERE " . implode(' AND ', $where) . "
     ORDER BY d.delivery_date DESC, d.id DESC",
    $params
);

$totalCost = 0;
$totalQuantity = 0;
foreach ($deliveries as $d) {
    $totalCost += $d['cost'];
    $totalQuantity += $d['quantity'];
}

$suppliers = db()->fetchAll("SELECT * FROM suppliers ORDER BY name");
$products = db()->fetchAll("SELECT * FROM products ORDER BY name");

include __DIR__ . '/_header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="flash flash--error">
        <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Статистика -->
<div class="stats-grid">
    <div class="stat-card stat-card--blue">
        <div class="stat-card__label">Всего поставок</div>
        <div class="stat-card__value"><?= count($deliveries) ?></div>
    </div>
    <div class="stat-card stat-card--green">
        <div class="stat-card__label">Принято товара</div>
        <div class="stat-card__value"><?= $totalQuantity ?> шт.</div>
    </div>
    <div class="stat-card stat-card--orange">
        <div class="stat-card__label">Затраты на закупку</div>
        <div class="stat-card__value"><?= formatPrice($totalCost) ?></div>
    </div>
</div>

<!-- Фильтры -->
<form method="GET" class="admin-filters">
    <div class="form__group">
        <label class="form__label">Поставщик</label>
        <select name="supplier" class="form__select">
            <option value="">Все</option>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $supplierFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form__group">
        <label class="form__label">С</label>
        <input type="date" name="date_from" class="form__input" value="<?= e($dateFrom) ?>">
    </div>
    <div class="form__group">
        <label class="form__label">По</label>
        <input type="date" name="date_to" class="form__input" value="<?= e($dateTo) ?>">
    </div>
    <button type="submit" class="btn btn--primary">Фильтр</button>
    <a href="?" class="btn btn--outline">Сброс</a>
</form>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
    <!-- Список поставок -->
    <div>
        <h3 class="mb-2">История поставок</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Поставщик</th>
                    <th>Товар</th>
                    <th>Кол-во</th>
                    <th>Стоимость</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($deliveries)): ?>
                <tr><td colspan="6" class="text-center" style="padding: 30px;">Нет поставок</td></tr>
            <?php else: ?>
                <?php foreach ($deliveries as $d): ?>
                    <tr>
                        <td><?= formatDate($d['delivery_date']) ?></td>
                        <td><?= e($d['supplier_name']) ?></td>
                        <td><?= e($d['product_name']) ?></td>
                        <td><strong><?= $d['quantity'] ?></strong> шт.</td>
                        <td><?= formatPrice($d['cost']) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button type="submit" class="btn btn--outline btn--small" 
                                        data-confirm="Удалить поставку? Остатки будут уменьшены.">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Форма приёма поставки -->
    <div>
        <h3 class="mb-2">Принять поставку</h3>
        <form method="POST" class="admin-form" data-validate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">

            <div class="form__group">
                <label class="form__label form__label--required">Поставщик</label>
                <select name="supplier_id" class="form__select" required>
                    <option value="">— выберите —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form__group">
                <label class="form__label form__label--required">Товар</label>
                <select name="product_id" class="form__select" required>
                    <option value="">— выберите —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (остаток: <?= $p['stock_quantity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form__group">
                <label class="form__label form__label--required">Количество, шт.</label>
                <input type="number" name="quantity" class="form__input" min="1" required>
            </div>

            <div class="form__group">
                <label class="form__label form__label--required">Дата поставки</label>
                <input type="date" name="delivery_date" class="form__input" 
                       value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form__group">
                <label class="form__label form__label--required">Стоимость закупки, ₽</label>
                <input type="number" name="cost" class="form__input" min="0" step="0.01" required>
            </div>

            <div class="flash flash--info" style="background: #e3f2fd; border-color: #2196f3; color: #0d47a1; margin: 12px 0;">
                ℹ️ Остаток товара на складе будет автоматически увеличен
            </div>

            <button type="submit" class="btn btn--primary btn--block">Принять поставку</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
