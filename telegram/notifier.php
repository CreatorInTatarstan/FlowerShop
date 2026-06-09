<?php
/**
 * Telegram Notifier - функции отправки уведомлений о событиях магазина
 */

if (!defined('FLOWER_SHOP')) {
    die('Прямой доступ запрещён');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

/**
 * Низкоуровневая отправка с логированием
 */
function tgNotify(int $chatId, string $message, string $eventType = 'generic', array $options = []): bool
{
    if (!tgIsConfigured() || $chatId <= 0) {
        return false;
    }

    $result = tg()->sendMessage($chatId, $message, $options);
    $success = !empty($result['ok']);

    // Логируем
    try {
        db()->query(
            "INSERT INTO telegram_notifications_log (telegram_id, event_type, message, success, error) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $chatId,
                $eventType,
                mb_substr($message, 0, 2000),
                $success ? 1 : 0,
                $success ? null : ($result['description'] ?? 'Unknown error')
            ]
        );
    } catch (Exception $e) {
        // Логирование - не критично
    }

    return $success;
}

/**
 * Уведомить всех админов
 */
function tgNotifyAdmins(string $message, string $eventType = 'admin', array $options = []): int
{
    $sent = 0;

    // Берём из настроек список chat_id, через запятую
    $idsStr = tgGetSetting('admin_chat_ids', '');
    $ids = [];
    if (!empty($idsStr)) {
        $ids = array_filter(array_map('intval', explode(',', $idsStr)));
    }

    // Плюс все пользователи с ролью admin/manager, у которых привязан Telegram
    try {
        $adminUsers = db()->fetchAll(
            "SELECT telegram_id FROM users 
             WHERE role IN ('admin', 'manager') 
             AND telegram_id IS NOT NULL 
             AND tg_notifications = 1"
        );
        foreach ($adminUsers as $u) {
            $ids[] = (int)$u['telegram_id'];
        }
    } catch (Exception $e) {}

    $ids = array_unique(array_filter($ids));

    foreach ($ids as $id) {
        if (tgNotify($id, $message, $eventType, $options)) {
            $sent++;
        }
    }
    return $sent;
}

// =====================================================
// СОБЫТИЯ МАГАЗИНА
// =====================================================

/**
 * Уведомление о новом заказе
 */
function tgNotifyOrderCreated(int $orderId): void
{
    if (!tgIsConfigured()) return;

    $order = db()->fetchOne(
        "SELECT o.*, u.name AS user_name, u.email AS user_email, u.telegram_id AS user_tg
         FROM orders o
         LEFT JOIN users u ON o.user_id = u.id
         WHERE o.id = ?", [$orderId]
    );
    if (!$order) return;

    $items = db()->fetchAll(
        "SELECT oi.*, p.name FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = ?", [$orderId]
    );

    // Текст для клиента
    $itemsList = '';
    foreach ($items as $it) {
        $itemsList .= "• " . htmlspecialchars($it['name']) . " — {$it['quantity']} шт. × " . number_format($it['price'], 0, '.', ' ') . " ₽\n";
    }

    $deliveryDate = date('d.m.Y', strtotime($order['delivery_date']));

    // КЛИЕНТУ (если привязан Telegram)
    if ($order['user_tg']) {
        $msgClient = "🌸 <b>Спасибо за заказ!</b>\n\n"
            . "Ваш заказ <b>№{$order['id']}</b> успешно оформлен.\n\n"
            . "<b>📋 Состав:</b>\n{$itemsList}\n"
            . "<b>💰 Сумма:</b> " . number_format($order['total_amount'], 0, '.', ' ') . " ₽\n"
            . "<b>📅 Доставка:</b> {$deliveryDate}, {$order['delivery_time']}\n"
            . "<b>📍 Адрес:</b> " . htmlspecialchars($order['delivery_address']) . "\n\n"
            . "Мы свяжемся с вами для подтверждения. ✨";

        tgNotify((int)$order['user_tg'], $msgClient, 'order_created_client', [
            'reply_markup' => tgInlineKeyboard([
                [tgButton('📦 Статус заказа', "order_status:{$order['id']}")],
                [tgButton('🛍 К каталогу', 'catalog')]
            ])
        ]);
    }

    // АДМИНАМ
    if (tgGetSetting('notify_admins_on_order') !== '0') {
        $msgAdmin = "🆕 <b>Новый заказ №{$order['id']}</b>\n\n"
            . "<b>👤 Получатель:</b> " . htmlspecialchars($order['recipient_name']) . "\n"
            . "<b>📞 Телефон:</b> " . htmlspecialchars($order['recipient_phone']) . "\n"
            . ($order['user_email'] ? "<b>✉️ Email:</b> " . htmlspecialchars($order['user_email']) . "\n" : '')
            . "\n<b>📋 Состав:</b>\n{$itemsList}\n"
            . "<b>💰 Сумма:</b> " . number_format($order['total_amount'], 0, '.', ' ') . " ₽\n"
            . "<b>💳 Оплата:</b> " . paymentMethodName($order['payment_method']) . "\n"
            . "<b>📅 Доставка:</b> {$deliveryDate}, {$order['delivery_time']}\n"
            . "<b>📍 Адрес:</b> " . htmlspecialchars($order['delivery_address']) . "\n"
            . ($order['comment'] ? "<b>💬 Комментарий:</b> " . htmlspecialchars($order['comment']) . "\n" : '');

        tgNotifyAdmins($msgAdmin, 'order_created_admin', [
            'reply_markup' => tgInlineKeyboard([
                [tgButton('▶️ В обработку', "set_status:{$order['id']}:processing")],
                [tgButton('🚚 На доставку', "set_status:{$order['id']}:delivery")],
                [tgButton('✅ Выполнен', "set_status:{$order['id']}:completed"),
                 tgButton('❌ Отменён', "set_status:{$order['id']}:cancelled")],
                [tgUrlButton('🔗 Открыть в админке', SITE_URL . '/admin/orders.php?view=' . $order['id'])]
            ])
        ]);
    }
}

/**
 * Уведомление об изменении статуса заказа
 */
function tgNotifyOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus): void
{
    if (!tgIsConfigured()) return;

    $order = db()->fetchOne(
        "SELECT o.*, u.telegram_id AS user_tg FROM orders o
         LEFT JOIN users u ON o.user_id = u.id
         WHERE o.id = ?", [$orderId]
    );
    if (!$order || !$order['user_tg']) return;

    $statusEmoji = [
        'new' => '🆕',
        'processing' => '⚙️',
        'delivery' => '🚚',
        'completed' => '✅',
        'cancelled' => '❌'
    ];
    $statusMsg = [
        'processing' => "Ваш заказ <b>принят в работу</b>. Флористы уже собирают ваш букет!",
        'delivery' => "Ваш заказ <b>отправлен в доставку</b>. Курьер скоро будет у получателя.",
        'completed' => "Ваш заказ <b>выполнен</b>. Спасибо, что выбрали нас! Будем рады вашему отзыву.",
        'cancelled' => "Ваш заказ был <b>отменён</b>. Если это ошибка — свяжитесь с нами."
    ];

    if (!isset($statusMsg[$newStatus])) return;

    $emoji = $statusEmoji[$newStatus] ?? '📦';
    $msg = "{$emoji} <b>Заказ №{$order['id']}</b>\n\n" . $statusMsg[$newStatus];

    tgNotify(
        (int)$order['user_tg'],
        $msg,
        'status_changed',
        [
            'reply_markup' => tgInlineKeyboard([
                [tgButton('📦 Подробнее', "order_status:{$order['id']}")]
            ])
        ]
    );
}

/**
 * Уведомление о новом отзыве (для админов)
 */
function tgNotifyNewReview(int $reviewId): void
{
    if (!tgIsConfigured()) return;
    if (tgGetSetting('notify_admins_on_review') === '0') return;

    $review = db()->fetchOne(
        "SELECT r.*, u.name AS user_name, p.name AS product_name 
         FROM reviews r
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN products p ON r.product_id = p.id
         WHERE r.id = ?", [$reviewId]
    );
    if (!$review) return;

    $stars = str_repeat('⭐', (int)$review['rating']);
    $msg = "⭐ <b>Новый отзыв</b>\n\n"
        . "<b>Товар:</b> " . htmlspecialchars($review['product_name']) . "\n"
        . "<b>Автор:</b> " . htmlspecialchars($review['user_name'] ?? 'Аноним') . "\n"
        . "<b>Оценка:</b> {$stars} ({$review['rating']}/5)\n\n"
        . "<i>" . htmlspecialchars(mb_substr($review['comment'], 0, 500)) . "</i>";

    tgNotifyAdmins($msg, 'new_review', [
        'reply_markup' => tgInlineKeyboard([
            [tgUrlButton('🔗 Открыть в админке', SITE_URL . '/admin/reviews.php')]
        ])
    ]);
}

/**
 * Уведомление о регистрации пользователя
 */
function tgNotifyUserRegistered(int $userId): void
{
    if (!tgIsConfigured()) return;

    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) return;

    $msg = "👤 <b>Новый пользователь зарегистрирован</b>\n\n"
        . "<b>Имя:</b> " . htmlspecialchars($user['name']) . "\n"
        . "<b>Email:</b> " . htmlspecialchars($user['email']) . "\n"
        . ($user['phone'] ? "<b>Телефон:</b> " . htmlspecialchars($user['phone']) . "\n" : '');

    tgNotifyAdmins($msg, 'user_registered');
}

/**
 * Уведомление об активности в корзине
 */
function tgNotifyCartAction(int $userId, string $action, string $productName): void
{
    if (!tgIsConfigured()) return;

    $user = db()->fetchOne(
        "SELECT telegram_id FROM users WHERE id = ? AND telegram_id IS NOT NULL AND tg_notifications = 1",
        [$userId]
    );
    if (!$user || !$user['telegram_id']) return;

    $actionMap = [
        'add' => '✅ Добавлено в корзину',
        'remove' => '🗑 Удалено из корзины',
        'clear' => '🧹 Корзина очищена'
    ];
    $title = $actionMap[$action] ?? '🛒 Действие в корзине';

    $msg = "{$title}\n\n<i>" . htmlspecialchars($productName) . "</i>";

    tgNotify(
        (int)$user['telegram_id'],
        $msg,
        'cart_' . $action,
        [
            'reply_markup' => tgInlineKeyboard([
                [tgButton('🛒 Открыть корзину', 'cart'),
                 tgUrlButton('🌐 На сайт', SITE_URL . '/pages/cart.php')]
            ])
        ]
    );
}
