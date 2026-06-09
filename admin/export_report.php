<?php
/**
 * Экспорт отчёта по заказам в CSV
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$dateFrom = clean($_GET['date_from'] ?? date('Y-m-01'));
$dateTo = clean($_GET['date_to'] ?? date('Y-m-d'));

// Получаем заказы за период
$orders = db()->fetchAll(
    "SELECT o.*, u.name AS user_name, u.email AS user_email,
            p.code AS promocode
     FROM orders o 
     LEFT JOIN users u ON o.user_id = u.id 
     LEFT JOIN promocodes p ON o.promocode_id = p.id
     WHERE o.created_at BETWEEN ? AND ?
     ORDER BY o.created_at DESC",
    [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
);

// Заголовки для скачивания
$filename = 'orders_report_' . $dateFrom . '_' . $dateTo . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM для корректного отображения кириллицы в Excel
echo "\xEF\xBB\xBF";

// Открываем поток
$out = fopen('php://output', 'w');

// Заголовки таблицы
fputcsv($out, [
    '№ заказа',
    'Дата создания',
    'Получатель',
    'Телефон',
    'Email клиента',
    'Адрес доставки',
    'Дата доставки',
    'Время доставки',
    'Способ оплаты',
    'Промокод',
    'Сумма, ₽',
    'Статус',
    'Комментарий'
], ';');

// Данные
foreach ($orders as $o) {
    fputcsv($out, [
        '#' . $o['id'],
        date('d.m.Y H:i', strtotime($o['created_at'])),
        $o['recipient_name'],
        $o['recipient_phone'],
        $o['user_email'] ?? '',
        $o['delivery_address'],
        date('d.m.Y', strtotime($o['delivery_date'])),
        $o['delivery_time'],
        paymentMethodName($o['payment_method']),
        $o['promocode'] ?? '',
        number_format($o['total_amount'], 2, '.', ''),
        orderStatusName($o['status']),
        $o['comment'] ?? ''
    ], ';');
}

// Итоговая строка
$totalAmount = array_sum(array_column($orders, 'total_amount'));
fputcsv($out, [], ';');
fputcsv($out, ['ИТОГО заказов:', count($orders), '', '', '', '', '', '', '', '', number_format($totalAmount, 2, '.', '')], ';');

fclose($out);
exit;
