<?php
/**
 * Аналитика и отчёты — главная страница админки
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Аналитика';

// Период анализа
$dateFrom = clean($_GET['date_from'] ?? date('Y-m-01'));
$dateTo = clean($_GET['date_to'] ?? date('Y-m-d'));

// Общая статистика
$totalOrders = (int)db()->fetchValue(
    "SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?",
    [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
);
$totalRevenue = (float)db()->fetchValue(
    "SELECT COALESCE(SUM(total_amount), 0) FROM orders 
     WHERE status IN ('completed', 'delivery', 'processing') 
     AND created_at BETWEEN ? AND ?",
    [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
);
$avgCheck = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
$totalProducts = (int)db()->fetchValue("SELECT COUNT(*) FROM products WHERE is_available = 1");
$totalUsers = (int)db()->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'client'");

// Заказы по статусам
$statusStats = db()->fetchAll(
    "SELECT status, COUNT(*) AS cnt 
     FROM orders 
     WHERE created_at BETWEEN ? AND ?
     GROUP BY status",
    [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
);

// Продажи по дням (последние 14 дней)
$salesByDay = db()->fetchAll(
    "SELECT DATE(created_at) AS day, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS sum
     FROM orders 
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day"
);

// Топ-10 товаров
$topProducts = db()->fetchAll(
    "SELECT p.id, p.name, p.price, 
            COALESCE(SUM(oi.quantity), 0) AS total_sold,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS total_revenue
     FROM products p
     LEFT JOIN order_items oi ON oi.product_id = p.id
     LEFT JOIN orders o ON oi.order_id = o.id 
       AND o.created_at BETWEEN ? AND ?
     GROUP BY p.id
     ORDER BY total_sold DESC
     LIMIT 10",
    [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
);

// Продажи по категориям
$byCategory = db()->fetchAll(
    "SELECT c.name, COUNT(DISTINCT o.id) AS orders_cnt, 
            COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     LEFT JOIN order_items oi ON oi.product_id = p.id
     LEFT JOIN orders o ON oi.order_id = o.id 
       AND o.created_at BETWEEN ? AND ?
     WHERE c.parent_id IS NULL
     GROUP BY c.id
     ORDER BY revenue DESC",
    [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
);

// Максимум для графика
$maxSales = 1;
foreach ($salesByDay as $day) {
    if ($day['sum'] > $maxSales) $maxSales = $day['sum'];
}

include __DIR__ . '/_header.php';
?>

<!-- Фильтр периода -->
<form method="GET" class="admin-filters">
    <div class="form__group">
        <label class="form__label">С даты</label>
        <input type="date" name="date_from" class="form__input" value="<?= e($dateFrom) ?>">
    </div>
    <div class="form__group">
        <label class="form__label">По дату</label>
        <input type="date" name="date_to" class="form__input" value="<?= e($dateTo) ?>">
    </div>
    <button type="submit" class="btn btn--primary">Применить</button>
    <a href="?" class="btn btn--outline">Сбросить</a>
    <a href="export_report.php?date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>" class="btn btn--secondary">📊 Экспорт CSV</a>
</form>

<!-- Карточки статистики -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__label">Заказов за период</div>
        <div class="stat-card__value"><?= $totalOrders ?></div>
    </div>
    <div class="stat-card stat-card--green">
        <div class="stat-card__label">Выручка</div>
        <div class="stat-card__value"><?= formatPrice($totalRevenue) ?></div>
    </div>
    <div class="stat-card stat-card--blue">
        <div class="stat-card__label">Средний чек</div>
        <div class="stat-card__value"><?= formatPrice($avgCheck) ?></div>
    </div>
    <div class="stat-card stat-card--orange">
        <div class="stat-card__label">Товаров в каталоге</div>
        <div class="stat-card__value"><?= $totalProducts ?></div>
        <div class="stat-card__sub">Клиентов: <?= $totalUsers ?></div>
    </div>
</div>

<!-- График продаж -->
<div class="chart-container">
    <h3>График продаж за последние 14 дней</h3>
    <?php if (!empty($salesByDay)): ?>
        <div class="chart-bars">
            <?php foreach ($salesByDay as $day): 
                $height = ($day['sum'] / $maxSales) * 100;
            ?>
                <div class="chart-bar" style="height: <?= max(2, $height) ?>%;" 
                     title="<?= formatDate($day['day']) ?>: <?= formatPrice($day['sum']) ?> (<?= $day['cnt'] ?> зак.)">
                    <span class="chart-bar__value"><?= number_format($day['sum'] / 1000, 1) ?>k</span>
                    <span style="margin-bottom: 4px;"><?= $day['cnt'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-labels">
            <?php foreach ($salesByDay as $day): ?>
                <div class="chart-label"><?= date('d.m', strtotime($day['day'])) ?></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">Нет данных за выбранный период</div>
    <?php endif; ?>
</div>

<!-- Заказы по статусам и категориям -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
    <div class="chart-container">
        <h3>Заказы по статусам</h3>
        <?php if (!empty($statusStats)): ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Статус</th><th>Кол-во</th></tr>
                </thead>
                <tbody>
                <?php foreach ($statusStats as $st): ?>
                    <tr>
                        <td><span class="status-badge <?= orderStatusClass($st['status']) ?>"><?= e(orderStatusName($st['status'])) ?></span></td>
                        <td><strong><?= $st['cnt'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Нет данных</p>
        <?php endif; ?>
    </div>

    <div class="chart-container">
        <h3>Продажи по категориям</h3>
        <table class="admin-table">
            <thead>
                <tr><th>Категория</th><th>Заказов</th><th>Выручка</th></tr>
            </thead>
            <tbody>
            <?php foreach ($byCategory as $cat): ?>
                <tr>
                    <td><?= e($cat['name']) ?></td>
                    <td><?= $cat['orders_cnt'] ?></td>
                    <td><strong><?= formatPrice($cat['revenue']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Топ товаров -->
<div class="chart-container">
    <h3>ТОП-10 товаров за период</h3>
    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Товар</th>
                <th>Цена</th>
                <th>Продано шт.</th>
                <th>Выручка</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($topProducts as $i => $p): ?>
            <tr>
                <td><strong>#<?= $i + 1 ?></strong></td>
                <td><a href="/admin/product_edit.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a></td>
                <td><?= formatPrice($p['price']) ?></td>
                <td><strong><?= (int)$p['total_sold'] ?></strong></td>
                <td><?= formatPrice($p['total_revenue']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
