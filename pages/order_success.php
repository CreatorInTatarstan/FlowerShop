<?php
/**
 * Страница успешного оформления заказа
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Заказ оформлен';
$orderId = (int)($_GET['id'] ?? 0);

$order = null;
if ($orderId) {
    $order = db()->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="cart-empty">
        <div class="cart-empty__icon">✅</div>
        <h2>Спасибо за заказ!</h2>
        <?php if ($order): ?>
            <p>Ваш заказ №<?= $order['id'] ?> на сумму <strong><?= formatPrice($order['total_amount']) ?></strong> успешно оформлен.</p>
            <p>Мы свяжемся с вами по телефону <strong><?= e($order['recipient_phone']) ?></strong> для подтверждения.</p>
            <p>Доставка <strong><?= formatDate($order['delivery_date']) ?></strong>, <?= e($order['delivery_time']) ?>.</p>
        <?php else: ?>
            <p>Ваш заказ принят в обработку.</p>
        <?php endif; ?>
        <div class="mt-3">
            <a href="/pages/catalog.php" class="btn btn--primary">Продолжить покупки</a>
            <?php if (isLoggedIn()): ?>
                <a href="/pages/account.php" class="btn btn--outline">Мои заказы</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
