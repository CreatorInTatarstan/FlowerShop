<?php
/**
 * Печать накладной по заказу
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$order = db()->fetchOne("SELECT * FROM orders WHERE id = ?", [$id]);

if (!$order) {
    die('Заказ не найден');
}

$items = db()->fetchAll(
    "SELECT oi.*, p.name FROM order_items oi 
     LEFT JOIN products p ON oi.product_id = p.id 
     WHERE oi.order_id = ?",
    [$id]
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Накладная №<?= $order['id'] ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; max-width: 800px; margin: 30px auto; padding: 20px; color: #2d2d2d; }
        h1 { border-bottom: 2px solid #d8527c; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        table th { background: #f7d6e2; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .info-block { background: #fdf8f5; padding: 16px; border-left: 4px solid #d8527c; }
        .total { font-size: 22px; font-weight: bold; text-align: right; margin-top: 20px; }
        .signature { margin-top: 60px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .signature div { border-top: 1px solid #000; padding-top: 8px; }
        .print-btn { margin: 20px 0; padding: 10px 20px; background: #d8527c; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Распечатать</button>

    <h1>🌸 Цветочная лавка</h1>
    <h2>Накладная №<?= $order['id'] ?> от <?= formatDate($order['created_at']) ?></h2>

    <div class="info-grid">
        <div class="info-block">
            <h3>Получатель</h3>
            <p><strong>ФИО:</strong> <?= e($order['recipient_name']) ?></p>
            <p><strong>Телефон:</strong> <?= e($order['recipient_phone']) ?></p>
        </div>
        <div class="info-block">
            <h3>Доставка</h3>
            <p><strong>Адрес:</strong> <?= e($order['delivery_address']) ?></p>
            <p><strong>Дата:</strong> <?= formatDate($order['delivery_date']) ?>, <?= e($order['delivery_time']) ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>Наименование</th>
                <th>Цена</th>
                <th>Кол-во</th>
                <th>Сумма</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $it): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($it['name']) ?></td>
                <td><?= formatPrice($it['price']) ?></td>
                <td><?= $it['quantity'] ?></td>
                <td><?= formatPrice($it['price'] * $it['quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total">Итого: <?= formatPrice($order['total_amount']) ?></div>

    <p><strong>Способ оплаты:</strong> <?= e(paymentMethodName($order['payment_method'])) ?></p>
    <?php if ($order['comment']): ?>
        <p><strong>Комментарий:</strong> <?= e($order['comment']) ?></p>
    <?php endif; ?>

    <div class="signature">
        <div>Курьер _______________ /__________________ /</div>
        <div>Получатель _______________ /__________________ /</div>
    </div>
</body>
</html>
