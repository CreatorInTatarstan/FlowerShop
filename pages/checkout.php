<?php
/**
 * Оформление заказа
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Оформление заказа';

$cart = getCartDetails();

// Если корзина пуста
if (empty($cart['items'])) {
    setFlash('error', 'Корзина пуста');
    redirect('/pages/cart.php');
}

$errors = [];
$user = currentUser();

$data = [
    'recipient_name' => $user['name'] ?? '',
    'recipient_phone' => $user['phone'] ?? '',
    'delivery_address' => '',
    'delivery_date' => date('Y-m-d', strtotime('+1 day')),
    'delivery_time' => '10:00-12:00',
    'payment_method' => 'card',
    'comment' => '',
    'guest_email' => $user['email'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Ошибка безопасности';
    } else {
        // Сбор данных
        foreach ($data as $key => $_) {
            if (isset($_POST[$key])) {
                $data[$key] = clean($_POST[$key]);
            }
        }

        // Валидация
        if (empty($data['recipient_name']) || mb_strlen($data['recipient_name']) < 2) {
            $errors[] = 'Укажите имя получателя';
        }
        if (!isValidPhone($data['recipient_phone'])) {
            $errors[] = 'Укажите корректный телефон';
        }
        if (empty($data['delivery_address'])) {
            $errors[] = 'Укажите адрес доставки';
        }
        if (empty($data['delivery_date']) || strtotime($data['delivery_date']) < strtotime('today')) {
            $errors[] = 'Укажите корректную дату доставки';
        }
        if (!in_array($data['payment_method'], ['cash', 'card', 'online'])) {
            $errors[] = 'Выберите способ оплаты';
        }

        // Если гость — нужен email для регистрации
        if (!isLoggedIn() && empty($data['guest_email'])) {
            $errors[] = 'Укажите email';
        }

        // Создание заказа
        if (empty($errors)) {
            try {
                db()->beginTransaction();

                // Если гость — создаём временного пользователя? Для простоты — оставляем NULL
                $userId = currentUserId();

                // Создаём заказ
                db()->query(
                    "INSERT INTO orders 
                     (user_id, total_amount, status, delivery_address, delivery_date, 
                      delivery_time, recipient_name, recipient_phone, payment_method, 
                      comment, promocode_id) 
                     VALUES (?, ?, 'new', ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $userId,
                        $cart['final'],
                        $data['delivery_address'],
                        $data['delivery_date'],
                        $data['delivery_time'],
                        $data['recipient_name'],
                        $data['recipient_phone'],
                        $data['payment_method'],
                        $data['comment'],
                        $cart['promocode']['id'] ?? null
                    ]
                );
                $orderId = (int)db()->lastInsertId();

                // Добавляем позиции
                foreach ($cart['items'] as $item) {
                    db()->query(
                        "INSERT INTO order_items (order_id, product_id, quantity, price) 
                         VALUES (?, ?, ?, ?)",
                        [$orderId, $item['id'], $item['quantity'], $item['price']]
                    );
                    // Уменьшаем остатки
                    db()->query(
                        "UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?",
                        [$item['quantity'], $item['id']]
                    );
                }

                db()->commit();

                // Очищаем корзину
                clearCart();
                logMessage("Создан заказ #$orderId на сумму {$cart['final']} ₽", 'INFO');

                // Уведомление в Telegram (клиенту и админам)
                if (file_exists(__DIR__ . '/../telegram/notifier.php')) {
                    require_once __DIR__ . '/../telegram/notifier.php';
                    try {
                        tgNotifyOrderCreated($orderId);
                    } catch (Throwable $e) {
                        logMessage('TG notify error: ' . $e->getMessage(), 'ERROR');
                    }
                }

                setFlash('success', "Заказ #$orderId успешно оформлен! Мы свяжемся с вами в ближайшее время.");
                redirect('/pages/order_success.php?id=' . $orderId);
            } catch (Exception $ex) {
                db()->rollBack();
                $errors[] = 'Ошибка при оформлении заказа: ' . $ex->getMessage();
                logMessage('Ошибка заказа: ' . $ex->getMessage(), 'ERROR');
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="section__title" style="text-align: left;">Оформление заказа</h1>

    <?php if (!empty($errors)): ?>
        <div class="flash flash--error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" data-validate>
        <?= csrfField() ?>

        <div class="cart-layout">
            <div>
                <div class="form mb-2">
                    <h3 class="mb-2">Получатель</h3>

                    <div class="form__row">
                        <div class="form__group">
                            <label class="form__label form__label--required">Имя получателя</label>
                            <input type="text" name="recipient_name" class="form__input" 
                                   value="<?= e($data['recipient_name']) ?>" required>
                        </div>
                        <div class="form__group">
                            <label class="form__label form__label--required">Телефон</label>
                            <input type="tel" name="recipient_phone" class="form__input" 
                                   value="<?= e($data['recipient_phone']) ?>" required 
                                   placeholder="+7 (___) ___-__-__">
                        </div>
                    </div>

                    <?php if (!isLoggedIn()): ?>
                        <div class="form__group">
                            <label class="form__label form__label--required">Email для уведомлений</label>
                            <input type="email" name="guest_email" class="form__input" 
                                   value="<?= e($data['guest_email']) ?>" required>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form mb-2">
                    <h3 class="mb-2">Доставка</h3>

                    <div class="form__group">
                        <label class="form__label form__label--required">Адрес доставки</label>
                        <input type="text" name="delivery_address" class="form__input" 
                               value="<?= e($data['delivery_address']) ?>" required 
                               placeholder="Город, улица, дом, квартира">
                    </div>

                    <div class="form__row">
                        <div class="form__group">
                            <label class="form__label form__label--required">Дата доставки</label>
                            <input type="date" name="delivery_date" class="form__input" 
                                   value="<?= e($data['delivery_date']) ?>" required
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        <div class="form__group">
                            <label class="form__label form__label--required">Время доставки</label>
                            <select name="delivery_time" class="form__select" required>
                                <option value="08:00-10:00" <?= $data['delivery_time'] === '08:00-10:00' ? 'selected' : '' ?>>08:00 — 10:00</option>
                                <option value="10:00-12:00" <?= $data['delivery_time'] === '10:00-12:00' ? 'selected' : '' ?>>10:00 — 12:00</option>
                                <option value="12:00-14:00" <?= $data['delivery_time'] === '12:00-14:00' ? 'selected' : '' ?>>12:00 — 14:00</option>
                                <option value="14:00-16:00" <?= $data['delivery_time'] === '14:00-16:00' ? 'selected' : '' ?>>14:00 — 16:00</option>
                                <option value="16:00-18:00" <?= $data['delivery_time'] === '16:00-18:00' ? 'selected' : '' ?>>16:00 — 18:00</option>
                                <option value="18:00-20:00" <?= $data['delivery_time'] === '18:00-20:00' ? 'selected' : '' ?>>18:00 — 20:00</option>
                                <option value="20:00-22:00" <?= $data['delivery_time'] === '20:00-22:00' ? 'selected' : '' ?>>20:00 — 22:00</option>
                            </select>
                        </div>
                    </div>

                    <div class="form__group">
                        <label class="form__label">Комментарий к заказу</label>
                        <textarea name="comment" class="form__textarea" 
                                  placeholder="Открытка, пожелания..."><?= e($data['comment']) ?></textarea>
                    </div>
                </div>

                <div class="form mb-2">
                    <h3 class="mb-2">Способ оплаты</h3>

                    <div class="form__group">
                        <label style="display: block; padding: 12px; border: 2px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 8px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="card" <?= $data['payment_method'] === 'card' ? 'checked' : '' ?>>
                            💳 Картой при получении
                        </label>
                        <label style="display: block; padding: 12px; border: 2px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 8px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="cash" <?= $data['payment_method'] === 'cash' ? 'checked' : '' ?>>
                            💵 Наличными при получении
                        </label>
                        <label style="display: block; padding: 12px; border: 2px solid var(--color-border); border-radius: var(--radius-md); cursor: pointer;">
                            <input type="radio" name="payment_method" value="online" <?= $data['payment_method'] === 'online' ? 'checked' : '' ?>>
                            🌐 Онлайн-оплата
                        </label>
                    </div>
                </div>
            </div>

            <!-- Сводка заказа -->
            <aside class="cart-summary">
                <h3 class="cart-summary__title">Ваш заказ</h3>

                <?php foreach ($cart['items'] as $item): ?>
                    <div class="cart-summary__row" style="font-size: 14px;">
                        <span><?= e($item['name']) ?> × <?= $item['quantity'] ?></span>
                        <span><?= formatPrice($item['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="cart-summary__row mt-2" style="border-top: 1px solid var(--color-border); padding-top: 12px;">
                    <span>Сумма:</span>
                    <span><?= formatPrice($cart['total']) ?></span>
                </div>

                <?php if ($cart['promocode']): ?>
                    <div class="cart-summary__row" style="color: var(--color-success);">
                        <span>Скидка <?= e($cart['promocode']['code']) ?>:</span>
                        <span>−<?= formatPrice($cart['discount']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="cart-summary__row">
                    <span>Доставка:</span>
                    <span style="color: var(--color-success);">Бесплатно</span>
                </div>

                <div class="cart-summary__total">
                    <div class="cart-summary__row" style="border: none; padding: 0;">
                        <span>К оплате:</span>
                        <span><?= formatPrice($cart['final']) ?></span>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--block btn--large">
                    Подтвердить заказ
                </button>

                <p class="text-muted text-center mt-2" style="font-size: 12px;">
                    Нажимая кнопку, вы соглашаетесь с условиями обработки персональных данных
                </p>
            </aside>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
