<?php
/**
 * Управление заказами
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Заказы';

// Просмотр конкретного заказа
$viewId = (int)($_GET['view'] ?? 0);

// Изменение статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    if (verifyCsrf($_POST['csrf_token'] ?? null)) {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = clean($_POST['status'] ?? '');
        if (in_array($status, ['new', 'processing', 'delivery', 'completed', 'cancelled'])) {
            $oldStatus = db()->fetchValue("SELECT status FROM orders WHERE id = ?", [$orderId]);
            db()->query("UPDATE orders SET status = ? WHERE id = ?", [$status, $orderId]);
            setFlash('success', "Статус заказа #$orderId изменён");
            logMessage("Изменён статус заказа #$orderId на '$status'", 'INFO');

            // Уведомление клиенту в Telegram
            if (file_exists(__DIR__ . '/../telegram/notifier.php') && $oldStatus !== $status) {
                require_once __DIR__ . '/../telegram/notifier.php';
                try {
                    tgNotifyOrderStatusChanged($orderId, $oldStatus ?: '', $status);
                } catch (Throwable $e) {
                    logMessage('TG notify error: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }
    redirect('/admin/orders.php' . ($viewId ? "?view=$viewId" : ''));
}

// =====================================================
// РЕЖИМ ПРОСМОТРА ОДНОГО ЗАКАЗА
// =====================================================
if ($viewId) {
    $order = db()->fetchOne(
        "SELECT o.*, u.name AS user_name, u.email AS user_email 
         FROM orders o 
         LEFT JOIN users u ON o.user_id = u.id 
         WHERE o.id = ?",
        [$viewId]
    );
    if (!$order) {
        setFlash('error', 'Заказ не найден');
        redirect('/admin/orders.php');
    }
    $items = db()->fetchAll(
        "SELECT oi.*, p.name, p.image FROM order_items oi 
         LEFT JOIN products p ON oi.product_id = p.id 
         WHERE oi.order_id = ?",
        [$viewId]
    );
    $pageTitle = "Заказ #$viewId";
    
    include __DIR__ . '/_header.php';
    ?>
    <a href="/admin/orders.php" class="btn btn--outline mb-3">← К списку заказов</a>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div>
            <div class="admin-form mb-3">
                <h3 class="mb-2">Состав заказа</h3>
                <table class="admin-table">
                    <thead>
                        <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): $sub = $it['price'] * $it['quantity']; ?>
                        <tr>
                            <td><?= e($it['name']) ?></td>
                            <td><?= formatPrice($it['price']) ?></td>
                            <td><?= $it['quantity'] ?></td>
                            <td><strong><?= formatPrice($sub) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr style="background: #f8f9fa;">
                            <td colspan="3" style="text-align: right;"><strong>Итого:</strong></td>
                            <td><strong><?= formatPrice($order['total_amount']) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="admin-form">
                <h3 class="mb-2">Получатель и доставка</h3>
                <table class="admin-table">
                    <tr><th style="width: 40%;">Имя получателя:</th><td><?= e($order['recipient_name']) ?></td></tr>
                    <tr><th>Телефон:</th><td><?= e($order['recipient_phone']) ?></td></tr>
                    <tr><th>Адрес доставки:</th><td><?= e($order['delivery_address']) ?></td></tr>
                    <tr><th>Дата доставки:</th><td><?= formatDate($order['delivery_date']) ?></td></tr>
                    <tr><th>Время доставки:</th><td><?= e($order['delivery_time']) ?></td></tr>
                    <tr><th>Способ оплаты:</th><td><?= e(paymentMethodName($order['payment_method'])) ?></td></tr>
                    <?php if ($order['comment']): ?>
                        <tr><th>Комментарий:</th><td><?= nl2br(e($order['comment'])) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($order['user_id']): ?>
                        <tr><th>Клиент:</th><td><?= e($order['user_name']) ?> (<?= e($order['user_email']) ?>)</td></tr>
                    <?php endif; ?>
                </table>
                <a href="invoice.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn--secondary mt-2">🖨️ Печать накладной</a>
            </div>
        </div>

        <aside>
            <div class="admin-form">
                <h3 class="mb-2">Управление</h3>
                <p><strong>Заказ:</strong> #<?= $order['id'] ?></p>
                <p><strong>Дата:</strong> <?= formatDate($order['created_at'], true) ?></p>
                <p><strong>Текущий статус:</strong> <span class="status-badge <?= orderStatusClass($order['status']) ?>"><?= e(orderStatusName($order['status'])) ?></span></p>

                <form method="POST" class="mt-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                    <div class="form__group">
                        <label class="form__label">Изменить статус:</label>
                        <select name="status" class="form__select">
                            <?php foreach (['new', 'processing', 'delivery', 'completed', 'cancelled'] as $st): ?>
                                <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= orderStatusName($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block">Сохранить</button>
                </form>
            </div>
        </aside>
    </div>
    <?php
    include __DIR__ . '/_footer.php';
    exit;
}

// =====================================================
// РЕЖИМ СПИСКА ЗАКАЗОВ
// =====================================================
$status = clean($_GET['status'] ?? '');
$dateFrom = clean($_GET['date_from'] ?? '');
$dateTo = clean($_GET['date_to'] ?? '');
$search = clean($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if ($status) {
    $where[] = "o.status = ?";
    $params[] = $status;
}
if ($dateFrom) {
    $where[] = "DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}
if ($search) {
    if (ctype_digit($search)) {
        $where[] = "(o.id = ? OR o.recipient_name LIKE ? OR o.recipient_phone LIKE ?)";
        $params[] = (int)$search;
        $params[] = "%$search%";
        $params[] = "%$search%";
    } else {
        $where[] = "(o.recipient_name LIKE ? OR o.recipient_phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

$whereSQL = implode(' AND ', $where);

$orders = db()->fetchAll(
    "SELECT o.*, u.name AS user_name 
     FROM orders o 
     LEFT JOIN users u ON o.user_id = u.id 
     WHERE $whereSQL
     ORDER BY o.created_at DESC",
    $params
);

include __DIR__ . '/_header.php';
?>

<form method="GET" class="admin-filters">
    <div class="form__group">
        <label class="form__label">Поиск (ID, имя, телефон)</label>
        <input type="text" name="search" class="form__input" value="<?= e($search) ?>">
    </div>
    <div class="form__group">
        <label class="form__label">Статус</label>
        <select name="status" class="form__select">
            <option value="">Все</option>
            <?php foreach (['new', 'processing', 'delivery', 'completed', 'cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= orderStatusName($st) ?></option>
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

<table class="admin-table">
    <thead>
        <tr>
            <th>№</th>
            <th>Дата</th>
            <th>Получатель</th>
            <th>Телефон</th>
            <th>Доставка</th>
            <th>Сумма</th>
            <th>Статус</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($orders)): ?>
        <tr><td colspan="8" class="text-center" style="padding: 30px;">Заказов не найдено</td></tr>
    <?php else: ?>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong>#<?= $o['id'] ?></strong></td>
                <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
                <td><?= e($o['recipient_name']) ?></td>
                <td><?= e($o['recipient_phone']) ?></td>
                <td><?= date('d.m.Y', strtotime($o['delivery_date'])) ?><br><small><?= e($o['delivery_time']) ?></small></td>
                <td><strong><?= formatPrice($o['total_amount']) ?></strong></td>
                <td><span class="status-badge <?= orderStatusClass($o['status']) ?>"><?= e(orderStatusName($o['status'])) ?></span></td>
                <td class="admin-table__actions">
                    <a href="?view=<?= $o['id'] ?>" class="btn btn--outline btn--small">Открыть</a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php include __DIR__ . '/_footer.php'; ?>
