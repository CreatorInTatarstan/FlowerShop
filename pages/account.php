<?php
/**
 * Личный кабинет пользователя
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$pageTitle = 'Личный кабинет';
$user = currentUser();
$tab = clean($_GET['tab'] ?? 'orders');

// Повтор заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    if (verifyCsrf($_POST['csrf_token'] ?? null)) {
        $orderId = (int)($_POST['order_id'] ?? 0);
        // Проверяем, что заказ принадлежит пользователю
        $order = db()->fetchOne(
            "SELECT * FROM orders WHERE id = ? AND user_id = ?",
            [$orderId, currentUserId()]
        );
        if ($order) {
            $items = db()->fetchAll(
                "SELECT * FROM order_items WHERE order_id = ?",
                [$orderId]
            );
            $added = 0;
            foreach ($items as $item) {
                $product = db()->fetchOne(
                    "SELECT * FROM products WHERE id = ? AND is_available = 1",
                    [$item['product_id']]
                );
                if ($product) {
                    addToCart((int)$item['product_id'], (int)$item['quantity']);
                    $added++;
                }
            }
            setFlash('success', "Добавлено в корзину: $added товар(ов)");
            redirect('/pages/cart.php');
        }
    }
}

// Обновление профиля
$profileError = '';
$profileSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $profileError = 'Ошибка безопасности';
    } else {
        $name = clean($_POST['name'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        if (mb_strlen($name) < 2) {
            $profileError = 'Введите имя';
        } else {
            db()->query(
                "UPDATE users SET name = ?, phone = ? WHERE id = ?",
                [$name, $phone, currentUserId()]
            );
            $_SESSION['user_name'] = $name;
            $profileSuccess = 'Профиль обновлён';
            $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [currentUserId()]);
        }
    }
}

// История заказов
$orders = db()->fetchAll(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
    [currentUserId()]
);

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="section__title" style="text-align: left;">Личный кабинет</h1>

    <div class="account-layout">
        <aside class="account-menu">
            <a href="?tab=orders" class="account-menu__item <?= $tab === 'orders' ? 'active' : '' ?>">
                📦 Мои заказы (<?= count($orders) ?>)
            </a>
            <a href="?tab=profile" class="account-menu__item <?= $tab === 'profile' ? 'active' : '' ?>">
                👤 Профиль
            </a>
            <a href="/pages/account_telegram.php" class="account-menu__item">
                📱 Telegram
            </a>
            <a href="/logout.php" class="account-menu__item">
                🚪 Выйти
            </a>
        </aside>

        <div>
            <?php if ($tab === 'profile'): ?>
                <h2 class="mb-2">Профиль</h2>

                <?php if ($profileError): ?>
                    <div class="flash flash--error"><?= e($profileError) ?></div>
                <?php endif; ?>
                <?php if ($profileSuccess): ?>
                    <div class="flash flash--success"><?= e($profileSuccess) ?></div>
                <?php endif; ?>

                <form method="POST" class="form" data-validate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form__group">
                        <label class="form__label">Email</label>
                        <input type="email" class="form__input" value="<?= e($user['email']) ?>" disabled>
                    </div>

                    <div class="form__row">
                        <div class="form__group">
                            <label class="form__label form__label--required">Имя</label>
                            <input type="text" name="name" class="form__input" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="form__group">
                            <label class="form__label">Телефон</label>
                            <input type="tel" name="phone" class="form__input" value="<?= e($user['phone']) ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary">Сохранить изменения</button>
                </form>
            <?php else: ?>
                <h2 class="mb-2">Мои заказы</h2>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">📦</div>
                        <h3>У вас пока нет заказов</h3>
                        <p>Оформите первый заказ в нашем каталоге</p>
                        <a href="/pages/catalog.php" class="btn btn--primary mt-2">В каталог</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): 
                        $items = db()->fetchAll(
                            "SELECT oi.*, p.name, p.image FROM order_items oi
                             LEFT JOIN products p ON oi.product_id = p.id
                             WHERE oi.order_id = ?",
                            [$order['id']]
                        );
                    ?>
                        <div class="form mb-2">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--color-border);">
                                <div>
                                    <strong>Заказ №<?= $order['id'] ?></strong>
                                    <span class="text-muted"> от <?= formatDate($order['created_at'], true) ?></span>
                                </div>
                                <span class="status-badge <?= orderStatusClass($order['status']) ?>">
                                    <?= e(orderStatusName($order['status'])) ?>
                                </span>
                            </div>

                            <div style="margin-bottom: 16px;">
                                <?php foreach ($items as $item): ?>
                                    <div style="display: flex; gap: 12px; align-items: center; padding: 8px 0;">
                                        <div style="width: 60px; height: 60px; background: var(--color-bg-alt); border-radius: var(--radius-sm); overflow: hidden;">
                                            <img src="<?= e(productImage($item['image'])) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <div style="flex: 1;">
                                            <div><?= e($item['name']) ?></div>
                                            <div class="text-muted"><?= formatPrice($item['price']) ?> × <?= $item['quantity'] ?></div>
                                        </div>
                                        <div><strong><?= formatPrice($item['price'] * $item['quantity']) ?></strong></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="font-size: 14px; line-height: 1.8;">
                                <div><strong>Доставка:</strong> <?= formatDate($order['delivery_date']) ?>, <?= e($order['delivery_time']) ?></div>
                                <div><strong>Адрес:</strong> <?= e($order['delivery_address']) ?></div>
                                <div><strong>Оплата:</strong> <?= e(paymentMethodName($order['payment_method'])) ?></div>
                                <div style="margin-top: 8px; font-size: 18px;"><strong>Итого: <?= formatPrice($order['total_amount']) ?></strong></div>
                            </div>

                            <form method="POST" class="mt-2" style="margin: 16px 0 0;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reorder">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn--outline btn--small">🔄 Повторить заказ</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
