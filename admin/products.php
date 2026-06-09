<?php
/**
 * Управление товарами
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Товары';

// Удаление
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verifyCsrf($_POST['csrf_token'] ?? null)) {
        $id = (int)($_POST['id'] ?? 0);
        // Проверяем, есть ли заказы
        $hasOrders = db()->fetchValue(
            "SELECT COUNT(*) FROM order_items WHERE product_id = ?", [$id]
        );
        if ($hasOrders) {
            // Не удаляем, а делаем недоступным
            db()->query("UPDATE products SET is_available = 0 WHERE id = ?", [$id]);
            setFlash('success', 'Товар скрыт (есть в заказах)');
        } else {
            db()->query("DELETE FROM products WHERE id = ?", [$id]);
            setFlash('success', 'Товар удалён');
        }
    }
    redirect('/admin/products.php');
}

// Фильтры
$categoryId = (int)($_GET['category'] ?? 0);
$search = clean($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if ($categoryId) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryId;
}
if ($search) {
    $where[] = "p.name LIKE ?";
    $params[] = "%$search%";
}

$products = db()->fetchAll(
    "SELECT p.*, c.name AS category_name 
     FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id 
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.id DESC",
    $params
);

$categories = db()->fetchAll("SELECT * FROM categories ORDER BY name");

include __DIR__ . '/_header.php';
?>

<div style="display: flex; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <a href="product_edit.php" class="btn btn--primary">+ Добавить товар</a>
</div>

<form method="GET" class="admin-filters">
    <div class="form__group">
        <label class="form__label">Поиск</label>
        <input type="text" name="search" class="form__input" value="<?= e($search) ?>">
    </div>
    <div class="form__group">
        <label class="form__label">Категория</label>
        <select name="category" class="form__select">
            <option value="">Все</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn--primary">Фильтр</button>
    <a href="?" class="btn btn--outline">Сброс</a>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Фото</th>
            <th>Название</th>
            <th>Категория</th>
            <th>Цена</th>
            <th>Остаток</th>
            <th>Доступен</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($products)): ?>
        <tr><td colspan="8" class="text-center" style="padding: 30px;">Товаров не найдено</td></tr>
    <?php else: ?>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td>
                    <img src="<?= e(productImage($p['image'])) ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                </td>
                <td><strong><?= e($p['name']) ?></strong></td>
                <td><?= e($p['category_name']) ?></td>
                <td><?= formatPrice($p['price']) ?></td>
                <td>
                    <?php if ($p['stock_quantity'] <= 0): ?>
                        <span style="color: #f44336;"><strong>0</strong></span>
                    <?php elseif ($p['stock_quantity'] < 5): ?>
                        <span style="color: #ff9800;"><strong><?= $p['stock_quantity'] ?></strong></span>
                    <?php else: ?>
                        <strong><?= $p['stock_quantity'] ?></strong>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $p['is_available'] ? '✓' : '✕' ?>
                </td>
                <td class="admin-table__actions">
                    <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn btn--outline btn--small">✏️</a>
                    <a href="/pages/product.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn--outline btn--small">👁️</a>
                    <form method="POST" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn--outline btn--small" data-confirm="Удалить товар?">🗑️</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php include __DIR__ . '/_footer.php'; ?>
