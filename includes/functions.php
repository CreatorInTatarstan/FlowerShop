<?php
/**
 * Файл с общими функциями системы
 */

if (!defined('FLOWER_SHOP')) {
    die('Прямой доступ запрещён');
}

// =====================================================
// БЕЗОПАСНОСТЬ
// =====================================================

/**
 * Защита от XSS - экранирование вывода
 */
function e(string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Очистка входных данных
 */
function clean(string $string): string
{
    return trim(strip_tags($string));
}

/**
 * Генерация CSRF-токена
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверка CSRF-токена
 */
function verifyCsrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) 
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Поле CSRF для формы
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

// =====================================================
// АВТОРИЗАЦИЯ
// =====================================================

/**
 * Проверка авторизован ли пользователь
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Получить ID текущего пользователя
 */
function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Получить данные текущего пользователя
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $user = db()->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [currentUserId()]
        );
    }
    return $user;
}

/**
 * Проверка роли
 */
function hasRole(string $role): bool
{
    $user = currentUser();
    return $user && $user['role'] === $role;
}

/**
 * Проверка админа или менеджера
 */
function isAdmin(): bool
{
    $user = currentUser();
    return $user && in_array($user['role'], ['admin', 'manager']);
}

/**
 * Требовать авторизацию
 */
function requireLogin(string $redirect = '/login.php'): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirect");
        exit;
    }
}

/**
 * Требовать права администратора
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: /login.php');
        exit;
    }
}

// =====================================================
// КОРЗИНА
// =====================================================

/**
 * Получить корзину
 */
function getCart(): array
{
    return $_SESSION['cart'] ?? [];
}

/**
 * Количество товаров в корзине
 */
function cartCount(): int
{
    $cart = getCart();
    return array_sum(array_column($cart, 'quantity'));
}

/**
 * Добавить товар в корзину
 */
function addToCart(int $productId, int $quantity = 1): void
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = [
            'product_id' => $productId,
            'quantity' => $quantity
        ];
    }
}

/**
 * Обновить количество товара в корзине
 */
function updateCartItem(int $productId, int $quantity): void
{
    if ($quantity <= 0) {
        removeFromCart($productId);
    } elseif (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
    }
}

/**
 * Удалить товар из корзины
 */
function removeFromCart(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

/**
 * Очистить корзину
 */
function clearCart(): void
{
    $_SESSION['cart'] = [];
    unset($_SESSION['promocode']);
}

/**
 * Получить детали корзины с товарами
 */
function getCartDetails(): array
{
    $cart = getCart();
    if (empty($cart)) return ['items' => [], 'total' => 0, 'discount' => 0, 'final' => 0];
    
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $products = db()->fetchAll(
        "SELECT * FROM products WHERE id IN ($placeholders)",
        $ids
    );
    
    $items = [];
    $total = 0;
    foreach ($products as $product) {
        $quantity = $cart[$product['id']]['quantity'];
        $subtotal = $product['price'] * $quantity;
        $items[] = array_merge($product, [
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ]);
        $total += $subtotal;
    }
    
    // Применение промокода
    $discount = 0;
    if (!empty($_SESSION['promocode'])) {
        $discount = $total * $_SESSION['promocode']['discount_percent'] / 100;
    }
    
    return [
        'items' => $items,
        'total' => $total,
        'discount' => $discount,
        'final' => $total - $discount,
        'promocode' => $_SESSION['promocode'] ?? null
    ];
}

// =====================================================
// ОБЩИЕ УТИЛИТЫ
// =====================================================

/**
 * Перенаправление
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Установить flash-сообщение
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Получить flash-сообщение
 */
function getFlash(string $type): ?string
{
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Форматирование цены
 */
function formatPrice($price): string
{
    return number_format((float)$price, 0, '.', ' ') . ' ₽';
}

/**
 * Форматирование даты
 */
function formatDate(string $date, bool $withTime = false): string
{
    $months = [
        '01' => 'января', '02' => 'февраля', '03' => 'марта',
        '04' => 'апреля', '05' => 'мая', '06' => 'июня',
        '07' => 'июля', '08' => 'августа', '09' => 'сентября',
        '10' => 'октября', '11' => 'ноября', '12' => 'декабря'
    ];
    $ts = strtotime($date);
    $result = date('j', $ts) . ' ' . $months[date('m', $ts)] . ' ' . date('Y', $ts);
    if ($withTime) {
        $result .= ', ' . date('H:i', $ts);
    }
    return $result;
}

/**
 * Перевод статуса заказа
 */
function orderStatusName(string $status): string
{
    $names = [
        'new' => 'Новый',
        'processing' => 'В обработке',
        'delivery' => 'В доставке',
        'completed' => 'Выполнен',
        'cancelled' => 'Отменён'
    ];
    return $names[$status] ?? $status;
}

/**
 * Класс CSS для статуса
 */
function orderStatusClass(string $status): string
{
    $classes = [
        'new' => 'status-new',
        'processing' => 'status-processing',
        'delivery' => 'status-delivery',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];
    return $classes[$status] ?? '';
}

/**
 * Перевод способа оплаты
 */
function paymentMethodName(string $method): string
{
    $names = [
        'cash' => 'Наличные при получении',
        'card' => 'Картой при получении',
        'online' => 'Онлайн-оплата'
    ];
    return $names[$method] ?? $method;
}

/**
 * Получить URL изображения товара
 */
function productImage(?string $image): string
{
    if (empty($image)) {
        return '/assets/images/no-image.svg';
    }
    $path = UPLOAD_PATH . '/' . $image;
    if (file_exists($path)) {
        return UPLOAD_URL . '/' . $image;
    }
    // Заглушка для тестовых данных
    return '/assets/images/placeholder.svg';
}

/**
 * Логирование
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    $logFile = ROOT_PATH . '/logs/app.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] [$level] $message" . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * Валидация email
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Валидация телефона
 */
function isValidPhone(string $phone): bool
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

/**
 * Транслитерация строки в slug
 */
function makeSlug(string $string): string
{
    $translit = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',' '=>'-'
    ];
    $string = mb_strtolower($string);
    $string = strtr($string, $translit);
    $string = preg_replace('/[^a-z0-9-]/', '', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}
