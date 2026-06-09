<?php
/**
 * Telegram Bot Webhook Handler
 * Обрабатывает входящие сообщения и callback'и от Telegram
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/notifier.php';

// Отвечаем сразу OK, чтобы Telegram не пытался переотправить
header('Content-Type: application/json');

// Читаем входящие данные
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    echo json_encode(['ok' => true]);
    exit;
}

logMessage('TG update: ' . substr($input, 0, 500), 'INFO');

try {
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallback($update['callback_query']);
    }
} catch (Throwable $e) {
    logMessage('TG bot error: ' . $e->getMessage(), 'ERROR');
}

echo json_encode(['ok' => true]);
exit;

// =====================================================
// ОСНОВНЫЕ ОБРАБОТЧИКИ
// =====================================================

function handleMessage(array $message): void
{
    $chatId = (int)$message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $from = $message['from'] ?? [];
    $username = $from['username'] ?? '';
    $firstName = $from['first_name'] ?? 'друг';

    // Обработка контакта (телефона)
    if (isset($message['contact'])) {
        handleContact($chatId, $message['contact']);
        return;
    }

    // Привязка по deep-link: /start TOKEN
    if (preg_match('#^/start\s+(\S+)$#', $text, $m)) {
        handleStartLink($chatId, $m[1], $username, $firstName);
        return;
    }

    // Команды
    if (strpos($text, '/') === 0) {
        handleCommand($chatId, $text, $firstName, $username);
        return;
    }

    // Состояние диалога?
    $state = getState($chatId);
    if ($state && !empty($state['state'])) {
        handleStateMessage($chatId, $text, $state);
        return;
    }

    // По умолчанию — главное меню
    showMainMenu($chatId, "Не понимаю команду. Используйте меню ниже.");
}

function handleCallback(array $callback): void
{
    $callbackId = $callback['id'];
    $chatId = (int)$callback['from']['id'];
    $data = $callback['data'] ?? '';
    $messageId = $callback['message']['message_id'] ?? 0;

    // Парсим callback_data
    $parts = explode(':', $data);
    $action = $parts[0];

    switch ($action) {
        case 'catalog':
            tg()->answerCallback($callbackId);
            showCatalog($chatId);
            break;

        case 'category':
            tg()->answerCallback($callbackId);
            showCategory($chatId, (int)$parts[1]);
            break;

        case 'product':
            tg()->answerCallback($callbackId);
            showProduct($chatId, (int)$parts[1]);
            break;

        case 'add_to_cart':
            $productId = (int)$parts[1];
            $product = db()->fetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
            if ($product) {
                addToCartTg($chatId, $productId);
                tg()->answerCallback($callbackId, '✅ Добавлено: ' . $product['name'], false);
                // Уведомить веб-пользователя если связан
                $u = db()->fetchOne("SELECT id FROM users WHERE telegram_id = ?", [$chatId]);
                if ($u) {
                    tgNotifyCartAction((int)$u['id'], 'add', $product['name']);
                }
            }
            break;

        case 'cart':
            tg()->answerCallback($callbackId);
            showCart($chatId);
            break;

        case 'cart_remove':
            removeFromCartTg($chatId, (int)$parts[1]);
            tg()->answerCallback($callbackId, '🗑 Удалено');
            showCart($chatId);
            break;

        case 'cart_clear':
            clearCartTg($chatId);
            tg()->answerCallback($callbackId, '🧹 Корзина очищена');
            showCart($chatId);
            break;

        case 'checkout':
            tg()->answerCallback($callbackId);
            startCheckout($chatId);
            break;

        case 'my_orders':
            tg()->answerCallback($callbackId);
            showMyOrders($chatId);
            break;

        case 'order_status':
            tg()->answerCallback($callbackId);
            showOrderStatus($chatId, (int)$parts[1]);
            break;

        case 'main_menu':
            tg()->answerCallback($callbackId);
            showMainMenu($chatId);
            break;

        case 'link_account':
            tg()->answerCallback($callbackId);
            showLinkAccount($chatId);
            break;

        case 'unlink':
            unlinkAccount($chatId);
            tg()->answerCallback($callbackId, '✅ Аккаунт отвязан');
            showMainMenu($chatId);
            break;

        case 'contacts':
            tg()->answerCallback($callbackId);
            showContacts($chatId);
            break;

        case 'about':
            tg()->answerCallback($callbackId);
            showAbout($chatId);
            break;

        case 'tg_time':
            $time = $parts[1] ?? '';
            $state = getState($chatId);
            if ($state && $state['state'] === 'checkout_time') {
                $data = $state['data_arr'] ?? [];
                $data['delivery_time'] = $time;
                setState($chatId, 'checkout_comment', $data);
                tg()->answerCallback($callbackId, 'Время выбрано: ' . $time);
                tg()->sendMessage($chatId, "💬 Добавьте комментарий к заказу (или отправьте «-» если не нужно):");
            } else {
                tg()->answerCallback($callbackId);
            }
            break;

        // === АДМИНСКИЕ ===
        case 'set_status':
            if (!isAdminTg($chatId)) {
                tg()->answerCallback($callbackId, '❌ Доступ запрещён', true);
                break;
            }
            $orderId = (int)$parts[1];
            $newStatus = $parts[2] ?? '';
            if (in_array($newStatus, ['new', 'processing', 'delivery', 'completed', 'cancelled'])) {
                $old = db()->fetchValue("SELECT status FROM orders WHERE id = ?", [$orderId]);
                db()->query("UPDATE orders SET status = ? WHERE id = ?", [$newStatus, $orderId]);
                tg()->answerCallback($callbackId, '✅ Статус изменён: ' . orderStatusName($newStatus));
                tgNotifyOrderStatusChanged($orderId, $old ?: '', $newStatus);
                tg()->sendMessage($chatId, "✅ Статус заказа №{$orderId} изменён на «" . orderStatusName($newStatus) . "»");
            }
            break;

        default:
            tg()->answerCallback($callbackId, 'Неизвестное действие');
    }
}

// =====================================================
// КОМАНДЫ
// =====================================================

function handleCommand(int $chatId, string $text, string $firstName, string $username): void
{
    $cmd = explode(' ', $text)[0];

    switch ($cmd) {
        case '/start':
            $greeting = "🌸 Добро пожаловать в <b>Цветочную лавку</b>, {$firstName}!\n\n"
                . "Здесь вы можете:\n"
                . "🛍 Посмотреть каталог букетов\n"
                . "🛒 Сделать заказ\n"
                . "📦 Отследить статус заказа\n"
                . "⭐ Получать уведомления о ваших заказах\n\n"
                . "Используйте меню ниже:";
            showMainMenu($chatId, $greeting);
            break;

        case '/catalog':
        case '/menu':
            showCatalog($chatId);
            break;

        case '/cart':
            showCart($chatId);
            break;

        case '/orders':
            showMyOrders($chatId);
            break;

        case '/help':
            $help = "🌸 <b>Команды бота</b>\n\n"
                . "/start — начать заново\n"
                . "/catalog — каталог букетов\n"
                . "/cart — моя корзина\n"
                . "/orders — мои заказы\n"
                . "/account — личный кабинет\n"
                . "/help — эта справка\n\n"
                . "Также вы можете использовать меню кнопок ниже сообщений.";
            tg()->sendMessage($chatId, $help);
            showMainMenu($chatId);
            break;

        case '/account':
            showLinkAccount($chatId);
            break;

        case '/cancel':
            clearState($chatId);
            showMainMenu($chatId, "❌ Операция отменена.");
            break;

        default:
            tg()->sendMessage($chatId, "Неизвестная команда. Используйте /help");
    }
}

// =====================================================
// СОСТОЯНИЯ И FSM
// =====================================================

function getState(int $chatId): ?array
{
    $row = db()->fetchOne("SELECT * FROM telegram_states WHERE telegram_id = ?", [$chatId]);
    if (!$row) return null;
    $row['data_arr'] = json_decode($row['data'] ?? '{}', true) ?: [];
    return $row;
}

function setState(int $chatId, string $state, array $data = []): void
{
    db()->query(
        "INSERT INTO telegram_states (telegram_id, state, data) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)",
        [$chatId, $state, json_encode($data, JSON_UNESCAPED_UNICODE)]
    );
}

function clearState(int $chatId): void
{
    db()->query("DELETE FROM telegram_states WHERE telegram_id = ?", [$chatId]);
}

function handleStateMessage(int $chatId, string $text, array $state): void
{
    $data = $state['data_arr'] ?? [];

    switch ($state['state']) {
        case 'checkout_name':
            if (mb_strlen($text) < 2) {
                tg()->sendMessage($chatId, '❌ Имя слишком короткое. Введите имя получателя:');
                return;
            }
            $data['recipient_name'] = $text;
            setState($chatId, 'checkout_phone', $data);
            tg()->sendMessage($chatId, "📞 Укажите телефон получателя:", [
                'reply_markup' => tgReplyKeyboard([
                    [tgContactButton('📱 Отправить мой телефон')]
                ], true)
            ]);
            break;

        case 'checkout_phone':
            $phone = preg_replace('/[^0-9+]/', '', $text);
            if (strlen($phone) < 10) {
                tg()->sendMessage($chatId, '❌ Некорректный телефон. Введите в формате +79991234567:');
                return;
            }
            $data['recipient_phone'] = $phone;
            setState($chatId, 'checkout_address', $data);
            tg()->sendMessage($chatId, "📍 Введите адрес доставки (улица, дом, квартира):", [
                'reply_markup' => tgRemoveKeyboard()
            ]);
            break;

        case 'checkout_address':
            if (mb_strlen($text) < 5) {
                tg()->sendMessage($chatId, '❌ Адрес слишком короткий. Введите полный адрес:');
                return;
            }
            $data['delivery_address'] = $text;
            setState($chatId, 'checkout_date', $data);
            tg()->sendMessage($chatId, "📅 Введите дату доставки (ДД.ММ.ГГГГ, не раньше завтра):");
            break;

        case 'checkout_date':
            $date = DateTime::createFromFormat('d.m.Y', $text);
            if (!$date || $date->format('Y-m-d') < date('Y-m-d', strtotime('+1 day'))) {
                tg()->sendMessage($chatId, '❌ Некорректная дата. Минимум завтрашняя. Формат: ДД.ММ.ГГГГ');
                return;
            }
            $data['delivery_date'] = $date->format('Y-m-d');
            setState($chatId, 'checkout_time', $data);
            tg()->sendMessage($chatId, "🕐 Выберите время доставки:", [
                'reply_markup' => tgInlineKeyboard([
                    [tgButton('08:00–10:00', 'tg_time:08:00-10:00'), tgButton('10:00–12:00', 'tg_time:10:00-12:00')],
                    [tgButton('12:00–14:00', 'tg_time:12:00-14:00'), tgButton('14:00–16:00', 'tg_time:14:00-16:00')],
                    [tgButton('16:00–18:00', 'tg_time:16:00-18:00'), tgButton('18:00–20:00', 'tg_time:18:00-20:00')],
                    [tgButton('20:00–22:00', 'tg_time:20:00-22:00')]
                ])
            ]);
            // На время — также callback. Обработаем здесь же через текст-парс
            break;

        case 'checkout_comment':
            $data['comment'] = $text === '-' ? '' : $text;
            finalizeOrder($chatId, $data);
            break;
    }
}

// Обработка времени через callback (дополним handleCallback)
// Для упрощения — добавим обработку tg_time: в начале handleCallback. Уже учтено: см. полное добавление ниже.

// =====================================================
// КОРЗИНА В БОТЕ (хранится в telegram_states.data['cart'])
// =====================================================

function getTgCart(int $chatId): array
{
    $state = getState($chatId);
    return $state['data_arr']['cart'] ?? [];
}

function saveTgCart(int $chatId, array $cart): void
{
    $state = getState($chatId);
    $data = $state['data_arr'] ?? [];
    $data['cart'] = $cart;
    setState($chatId, $state['state'] ?? 'idle', $data);
}

function addToCartTg(int $chatId, int $productId, int $quantity = 1): void
{
    $cart = getTgCart($chatId);
    if (isset($cart[$productId])) {
        $cart[$productId] += $quantity;
    } else {
        $cart[$productId] = $quantity;
    }
    saveTgCart($chatId, $cart);
}

function removeFromCartTg(int $chatId, int $productId): void
{
    $cart = getTgCart($chatId);
    unset($cart[$productId]);
    saveTgCart($chatId, $cart);
}

function clearCartTg(int $chatId): void
{
    saveTgCart($chatId, []);
}

// =====================================================
// ЭКРАНЫ
// =====================================================

function showMainMenu(int $chatId, string $text = ''): void
{
    if (empty($text)) {
        $text = "🌸 <b>Цветочная лавка</b>\n\nВыберите действие:";
    }

    $linked = db()->fetchOne("SELECT id, name FROM users WHERE telegram_id = ?", [$chatId]);
    $accountText = $linked ? '👤 ' . $linked['name'] : '🔗 Привязать аккаунт';

    tg()->sendMessage($chatId, $text, [
        'reply_markup' => tgInlineKeyboard([
            [tgButton('🛍 Каталог', 'catalog')],
            [tgButton('🛒 Корзина', 'cart'), tgButton('📦 Мои заказы', 'my_orders')],
            [tgButton($accountText, 'link_account')],
            [tgButton('ℹ️ О нас', 'about'), tgButton('📞 Контакты', 'contacts')],
            [tgUrlButton('🌐 Открыть сайт', SITE_URL)]
        ])
    ]);
}

function showCatalog(int $chatId): void
{
    $categories = db()->fetchAll("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY id");

    $icons = [
        'bouquets' => '💐', 'roses' => '🌹', 'tulips' => '🌷',
        'compositions' => '🌺', 'wedding' => '👰', 'birthday' => '🎂'
    ];

    $rows = [];
    $rowBuffer = [];
    foreach ($categories as $i => $cat) {
        $icon = $icons[$cat['slug']] ?? '🌼';
        $rowBuffer[] = tgButton($icon . ' ' . $cat['name'], 'category:' . $cat['id']);
        if (count($rowBuffer) === 2) {
            $rows[] = $rowBuffer;
            $rowBuffer = [];
        }
    }
    if (!empty($rowBuffer)) $rows[] = $rowBuffer;
    $rows[] = [tgButton('🔙 Главное меню', 'main_menu')];

    tg()->sendMessage($chatId, "🌸 <b>Каталог букетов</b>\n\nВыберите категорию:", [
        'reply_markup' => tgInlineKeyboard($rows)
    ]);
}

function showCategory(int $chatId, int $categoryId): void
{
    $category = db()->fetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
    if (!$category) return;

    $products = db()->fetchAll(
        "SELECT * FROM products WHERE category_id = ? AND is_available = 1 LIMIT 10",
        [$categoryId]
    );

    if (empty($products)) {
        tg()->sendMessage($chatId, "В категории «{$category['name']}» пока нет товаров.", [
            'reply_markup' => tgInlineKeyboard([[tgButton('🔙 В каталог', 'catalog')]])
        ]);
        return;
    }

    tg()->sendMessage($chatId, "🌸 <b>{$category['name']}</b>\n\nВыберите букет:");

    foreach ($products as $p) {
        $caption = "<b>" . htmlspecialchars($p['name']) . "</b>\n\n"
            . ($p['description'] ? mb_substr(htmlspecialchars($p['description']), 0, 200) . "\n\n" : '')
            . "💰 <b>" . number_format($p['price'], 0, '.', ' ') . " ₽</b>";

        $keyboard = tgInlineKeyboard([
            [tgButton('🛒 В корзину', 'add_to_cart:' . $p['id']), 
             tgButton('🔍 Подробнее', 'product:' . $p['id'])]
        ]);

        $photoUrl = '';
        if ($p['image']) {
            $photoUrl = SITE_URL . '/uploads/' . $p['image'];
        }
        if ($photoUrl && @getimagesize($photoUrl)) {
            tg()->sendPhoto($chatId, $photoUrl, $caption, ['reply_markup' => $keyboard]);
        } else {
            tg()->sendMessage($chatId, $caption, ['reply_markup' => $keyboard]);
        }
    }

    tg()->sendMessage($chatId, "Это все товары категории.", [
        'reply_markup' => tgInlineKeyboard([
            [tgButton('🔙 В каталог', 'catalog'), tgButton('🛒 Корзина', 'cart')]
        ])
    ]);
}

function showProduct(int $chatId, int $productId): void
{
    $p = db()->fetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
    if (!$p) {
        tg()->sendMessage($chatId, '❌ Товар не найден');
        return;
    }

    $text = "🌸 <b>" . htmlspecialchars($p['name']) . "</b>\n\n"
        . ($p['description'] ? htmlspecialchars($p['description']) . "\n\n" : '')
        . ($p['composition'] ? "<b>Состав:</b> " . htmlspecialchars($p['composition']) . "\n" : '')
        . ($p['size'] ? "<b>Размер:</b> " . htmlspecialchars($p['size']) . "\n" : '')
        . "\n💰 <b>" . number_format($p['price'], 0, '.', ' ') . " ₽</b>\n"
        . ($p['stock_quantity'] > 0 ? "✅ В наличии: {$p['stock_quantity']} шт." : "❌ Нет в наличии");

    $kb = [[tgButton('🛒 В корзину', 'add_to_cart:' . $p['id'])]];
    $kb[] = [tgButton('🔙 В каталог', 'catalog'), tgButton('🛒 Корзина', 'cart')];

    tg()->sendMessage($chatId, $text, ['reply_markup' => tgInlineKeyboard($kb)]);
}

function showCart(int $chatId): void
{
    $cart = getTgCart($chatId);
    if (empty($cart)) {
        tg()->sendMessage($chatId, "🛒 <b>Корзина пуста</b>\n\nЗагляните в каталог!", [
            'reply_markup' => tgInlineKeyboard([
                [tgButton('🛍 Каталог', 'catalog'), tgButton('🔙 Меню', 'main_menu')]
            ])
        ]);
        return;
    }

    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $products = db()->fetchAll("SELECT * FROM products WHERE id IN ($placeholders)", $ids);

    $text = "🛒 <b>Ваша корзина</b>\n\n";
    $total = 0;
    $kbRows = [];

    foreach ($products as $p) {
        $qty = $cart[$p['id']];
        $subtotal = $p['price'] * $qty;
        $total += $subtotal;
        $text .= "• " . htmlspecialchars($p['name']) . " — {$qty} шт. × " . number_format($p['price'], 0, '.', ' ') . " ₽ = <b>" . number_format($subtotal, 0, '.', ' ') . " ₽</b>\n";
        $kbRows[] = [tgButton('🗑 Убрать «' . mb_substr($p['name'], 0, 25) . '»', 'cart_remove:' . $p['id'])];
    }

    $text .= "\n<b>💰 Итого: " . number_format($total, 0, '.', ' ') . " ₽</b>";

    $kbRows[] = [tgButton('✅ Оформить заказ', 'checkout'), tgButton('🧹 Очистить', 'cart_clear')];
    $kbRows[] = [tgButton('🛍 Продолжить покупки', 'catalog'), tgButton('🔙 Меню', 'main_menu')];

    tg()->sendMessage($chatId, $text, ['reply_markup' => tgInlineKeyboard($kbRows)]);
}

function startCheckout(int $chatId): void
{
    $cart = getTgCart($chatId);
    if (empty($cart)) {
        tg()->sendMessage($chatId, '❌ Корзина пуста. Сначала добавьте товары.');
        return;
    }

    setState($chatId, 'checkout_name', ['cart' => $cart]);
    tg()->sendMessage($chatId, "📝 <b>Оформление заказа</b>\n\n👤 Введите имя получателя:");
}

function finalizeOrder(int $chatId, array $data): void
{
    $cart = $data['cart'] ?? [];
    if (empty($cart)) {
        tg()->sendMessage($chatId, '❌ Корзина пуста');
        clearState($chatId);
        return;
    }

    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $products = db()->fetchAll("SELECT * FROM products WHERE id IN ($placeholders)", $ids);

    $total = 0;
    foreach ($products as $p) {
        $total += $p['price'] * $cart[$p['id']];
    }

    // Привязанный пользователь
    $user = db()->fetchOne("SELECT id FROM users WHERE telegram_id = ?", [$chatId]);
    $userId = $user ? (int)$user['id'] : null;

    try {
        db()->beginTransaction();

        db()->query(
            "INSERT INTO orders 
             (user_id, total_amount, status, delivery_address, delivery_date, delivery_time, 
              recipient_name, recipient_phone, payment_method, comment) 
             VALUES (?, ?, 'new', ?, ?, ?, ?, ?, 'card', ?)",
            [
                $userId, $total,
                $data['delivery_address'], $data['delivery_date'], $data['delivery_time'],
                $data['recipient_name'], $data['recipient_phone'], $data['comment'] ?? ''
            ]
        );
        $orderId = (int)db()->lastInsertId();

        foreach ($products as $p) {
            db()->query(
                "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)",
                [$orderId, $p['id'], $cart[$p['id']], $p['price']]
            );
            db()->query(
                "UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?",
                [$cart[$p['id']], $p['id']]
            );
        }

        db()->commit();

        // Очищаем корзину и состояние
        clearState($chatId);

        // Уведомления (клиенту и админам)
        tgNotifyOrderCreated($orderId);

        tg()->sendMessage($chatId, 
            "✅ <b>Заказ №{$orderId} оформлен!</b>\n\nСумма: " . number_format($total, 0, '.', ' ') . " ₽\n\nМы свяжемся с вами для подтверждения.",
            [
                'reply_markup' => tgInlineKeyboard([
                    [tgButton('📦 Мои заказы', 'my_orders'), tgButton('🔙 Меню', 'main_menu')]
                ])
            ]
        );
    } catch (Throwable $ex) {
        db()->rollBack();
        logMessage('TG order error: ' . $ex->getMessage(), 'ERROR');
        tg()->sendMessage($chatId, '❌ Ошибка при создании заказа. Попробуйте позже.');
    }
}

function showMyOrders(int $chatId): void
{
    $user = db()->fetchOne("SELECT * FROM users WHERE telegram_id = ?", [$chatId]);
    if (!$user) {
        tg()->sendMessage($chatId, 
            "🔗 Чтобы видеть свои заказы, нужно <b>привязать аккаунт</b> сайта к Telegram.\n\nЛибо просто оформите заказ — мы сохраним его историю в боте.",
            ['reply_markup' => tgInlineKeyboard([
                [tgButton('🔗 Привязать аккаунт', 'link_account')],
                [tgButton('🔙 Меню', 'main_menu')]
            ])]
        );
        return;
    }

    $orders = db()->fetchAll(
        "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
        [$user['id']]
    );

    if (empty($orders)) {
        tg()->sendMessage($chatId, "📦 У вас пока нет заказов.", [
            'reply_markup' => tgInlineKeyboard([
                [tgButton('🛍 К каталогу', 'catalog'), tgButton('🔙 Меню', 'main_menu')]
            ])
        ]);
        return;
    }

    $text = "📦 <b>Ваши заказы</b>\n\n";
    foreach ($orders as $o) {
        $text .= "📋 <b>№{$o['id']}</b> от " . date('d.m.Y', strtotime($o['created_at'])) . "\n"
            . "Статус: <b>" . orderStatusName($o['status']) . "</b>\n"
            . "Сумма: " . number_format($o['total_amount'], 0, '.', ' ') . " ₽\n\n";
    }

    tg()->sendMessage($chatId, $text, [
        'reply_markup' => tgInlineKeyboard([[tgButton('🔙 Меню', 'main_menu')]])
    ]);
}

function showOrderStatus(int $chatId, int $orderId): void
{
    $order = db()->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) {
        tg()->sendMessage($chatId, '❌ Заказ не найден');
        return;
    }

    // Проверяем доступ (либо это заказ привязанного пользователя, либо админ)
    $user = db()->fetchOne("SELECT * FROM users WHERE telegram_id = ?", [$chatId]);
    if (!isAdminTg($chatId) && (!$user || $user['id'] != $order['user_id'])) {
        tg()->sendMessage($chatId, '❌ Доступ запрещён');
        return;
    }

    $text = "📦 <b>Заказ №{$order['id']}</b>\n\n"
        . "Статус: <b>" . orderStatusName($order['status']) . "</b>\n"
        . "Дата: " . date('d.m.Y H:i', strtotime($order['created_at'])) . "\n"
        . "Сумма: " . number_format($order['total_amount'], 0, '.', ' ') . " ₽\n"
        . "Доставка: " . date('d.m.Y', strtotime($order['delivery_date'])) . ", {$order['delivery_time']}\n"
        . "Адрес: " . htmlspecialchars($order['delivery_address']);

    tg()->sendMessage($chatId, $text, [
        'reply_markup' => tgInlineKeyboard([
            [tgButton('📦 Все заказы', 'my_orders'), tgButton('🔙 Меню', 'main_menu')]
        ])
    ]);
}

function showLinkAccount(int $chatId): void
{
    $user = db()->fetchOne("SELECT * FROM users WHERE telegram_id = ?", [$chatId]);

    if ($user) {
        $text = "✅ <b>Аккаунт привязан</b>\n\n"
            . "👤 Имя: " . htmlspecialchars($user['name']) . "\n"
            . "✉️ Email: " . htmlspecialchars($user['email']) . "\n"
            . "👔 Роль: " . htmlspecialchars($user['role']) . "\n\n"
            . "Вы получаете уведомления о ваших заказах в этот чат.";
        tg()->sendMessage($chatId, $text, [
            'reply_markup' => tgInlineKeyboard([
                [tgButton('🔓 Отвязать', 'unlink')],
                [tgButton('🔙 Меню', 'main_menu')]
            ])
        ]);
    } else {
        $text = "🔗 <b>Привязка аккаунта</b>\n\n"
            . "Чтобы видеть свои заказы и получать обновления статусов, привяжите аккаунт сайта:\n\n"
            . "1️⃣ Зайдите на сайт " . SITE_URL . " под своим аккаунтом\n"
            . "2️⃣ Перейдите в <b>Личный кабинет → Telegram</b>\n"
            . "3️⃣ Нажмите «Привязать Telegram»\n"
            . "4️⃣ Перейдите по ссылке, которая откроет этот бот";
        tg()->sendMessage($chatId, $text, [
            'reply_markup' => tgInlineKeyboard([
                [tgUrlButton('🌐 Открыть сайт', SITE_URL . '/login.php')],
                [tgButton('🔙 Меню', 'main_menu')]
            ])
        ]);
    }
}

function handleStartLink(int $chatId, string $token, string $username, string $firstName): void
{
    $user = db()->fetchOne(
        "SELECT * FROM users WHERE tg_link_token = ? AND tg_link_token != ''",
        [$token]
    );
    if (!$user) {
        tg()->sendMessage($chatId, '❌ Ссылка недействительна или истекла. Получите новую в личном кабинете на сайте.');
        showMainMenu($chatId);
        return;
    }

    // Привязываем
    db()->query(
        "UPDATE users SET telegram_id = ?, telegram_username = ?, tg_link_token = NULL WHERE id = ?",
        [$chatId, $username, $user['id']]
    );

    tg()->sendMessage($chatId,
        "✅ <b>Аккаунт привязан!</b>\n\nЗдравствуйте, " . htmlspecialchars($user['name']) . "!\n\nТеперь вы будете получать уведомления о ваших заказах прямо в этот чат."
    );
    showMainMenu($chatId);
}

function unlinkAccount(int $chatId): void
{
    db()->query("UPDATE users SET telegram_id = NULL, telegram_username = NULL WHERE telegram_id = ?", [$chatId]);
}

function showContacts(int $chatId): void
{
    $text = "📞 <b>Контакты</b>\n\n"
        . "📍 Адрес: г. Москва, ул. Цветочная, д. 1\n"
        . "🕐 Режим: Пн-Вс с 8:00 до 22:00\n"
        . "📞 Телефон: " . SITE_PHONE . "\n"
        . "✉️ Email: " . SITE_EMAIL;

    tg()->sendMessage($chatId, $text, [
        'reply_markup' => tgInlineKeyboard([
            [tgUrlButton('🌐 На сайт', SITE_URL . '/pages/contacts.php')],
            [tgButton('🔙 Меню', 'main_menu')]
        ])
    ]);
}

function showAbout(int $chatId): void
{
    $text = "🌸 <b>О Цветочной лавке</b>\n\n"
        . "Мы — команда флористов, которые любят своё дело.\n\n"
        . "🌹 Свежие цветы ежедневно\n"
        . "💐 Авторские букеты\n"
        . "🚚 Доставка от 2 часов\n"
        . "💝 Гарантия качества\n\n"
        . "Создаём букеты, которые делают людей счастливее.";

    tg()->sendMessage($chatId, $text, [
        'reply_markup' => tgInlineKeyboard([
            [tgUrlButton('🌐 На сайт', SITE_URL)],
            [tgButton('🔙 Меню', 'main_menu')]
        ])
    ]);
}

function handleContact(int $chatId, array $contact): void
{
    $state = getState($chatId);
    if ($state && $state['state'] === 'checkout_phone') {
        $data = $state['data_arr'] ?? [];
        $data['recipient_phone'] = $contact['phone_number'];
        setState($chatId, 'checkout_address', $data);
        tg()->sendMessage($chatId, "📍 Теперь укажите адрес доставки (улица, дом, квартира):", [
            'reply_markup' => tgRemoveKeyboard()
        ]);
    }
}

function isAdminTg(int $chatId): bool
{
    $user = db()->fetchOne(
        "SELECT role FROM users WHERE telegram_id = ?", [$chatId]
    );
    if ($user && in_array($user['role'], ['admin', 'manager'])) {
        return true;
    }
    // Также проверяем настройку admin_chat_ids
    $idsStr = tgGetSetting('admin_chat_ids', '');
    if (!empty($idsStr)) {
        $ids = array_map('intval', explode(',', $idsStr));
        if (in_array($chatId, $ids)) {
            return true;
        }
    }
    return false;
}
