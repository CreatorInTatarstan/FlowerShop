<?php
/**
 * Корзина
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Корзина';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Ошибка безопасности. Обновите страницу.');
        redirect('/pages/cart.php');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            
            // Проверяем наличие
            $product = db()->fetchOne(
                "SELECT * FROM products WHERE id = ? AND is_available = 1",
                [$productId]
            );
            if ($product && $product['stock_quantity'] >= $quantity) {
                addToCart($productId, $quantity);
                setFlash('success', 'Товар «' . $product['name'] . '» добавлен в корзину');
                
                // Уведомление в TG (если пользователь авторизован и связан с TG)
                if (isLoggedIn() && file_exists(__DIR__ . '/../telegram/notifier.php')) {
                    require_once __DIR__ . '/../telegram/notifier.php';
                    try {
                        tgNotifyCartAction(currentUserId(), 'add', $product['name']);
                    } catch (Throwable $e) {}
                }
            } else {
                setFlash('error', 'Товар недоступен или недостаточно на складе');
            }
            redirect('/pages/cart.php');
            break;

        case 'update':
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = max(0, (int)($_POST['quantity'] ?? 0));
            updateCartItem($productId, $quantity);
            redirect('/pages/cart.php');
            break;

        case 'remove':
            $productId = (int)($_POST['product_id'] ?? 0);
            $product = db()->fetchOne("SELECT name FROM products WHERE id = ?", [$productId]);
            removeFromCart($productId);
            setFlash('success', 'Товар удалён из корзины');
            
            if (isLoggedIn() && $product && file_exists(__DIR__ . '/../telegram/notifier.php')) {
                require_once __DIR__ . '/../telegram/notifier.php';
                try {
                    tgNotifyCartAction(currentUserId(), 'remove', $product['name']);
                } catch (Throwable $e) {}
            }
            redirect('/pages/cart.php');
            break;

        case 'clear':
            clearCart();
            setFlash('success', 'Корзина очищена');
            
            if (isLoggedIn() && file_exists(__DIR__ . '/../telegram/notifier.php')) {
                require_once __DIR__ . '/../telegram/notifier.php';
                try {
                    tgNotifyCartAction(currentUserId(), 'clear', 'Все товары');
                } catch (Throwable $e) {}
            }
            redirect('/pages/cart.php');
            break;

        case 'apply_promo':
            $code = strtoupper(clean($_POST['promocode'] ?? ''));
            $promo = db()->fetchOne(
                "SELECT * FROM promocodes 
                 WHERE code = ? AND is_active = 1 
                 AND (valid_until IS NULL OR valid_until >= CURDATE())",
                [$code]
            );
            if ($promo) {
                $_SESSION['promocode'] = [
                    'id' => $promo['id'],
                    'code' => $promo['code'],
                    'discount_percent' => $promo['discount_percent']
                ];
                setFlash('success', "Промокод применён! Скидка {$promo['discount_percent']}%");
            } else {
                setFlash('error', 'Промокод не найден или истёк');
            }
            redirect('/pages/cart.php');
            break;

        case 'remove_promo':
            unset($_SESSION['promocode']);
            setFlash('success', 'Промокод удалён');
            redirect('/pages/cart.php');
            break;
    }
}

$cart = getCartDetails();

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="section__title" style="text-align: left;">Корзина</h1>

    <?php if (empty($cart['items'])): ?>
        <div class="cart-empty">
            <div class="cart-empty__icon">🛒</div>
            <h2>Ваша корзина пуста</h2>
            <p>Загляните в каталог и выберите букет, который понравится</p>
            <a href="/pages/catalog.php" class="btn btn--primary btn--large">В каталог</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <!-- Список товаров -->
            <div class="cart-items">
                <?php foreach ($cart['items'] as $item): ?>
                    <div class="cart-item">
                        <a href="/pages/product.php?id=<?= $item['id'] ?>" class="cart-item__image">
                            <img src="<?= e(productImage($item['image'])) ?>" alt="<?= e($item['name']) ?>">
                        </a>
                        <div>
                            <div class="cart-item__name">
                                <a href="/pages/product.php?id=<?= $item['id'] ?>"><?= e($item['name']) ?></a>
                            </div>
                            <div class="cart-item__price"><?= formatPrice($item['price']) ?> × <?= $item['quantity'] ?></div>
                        </div>
                        <form method="POST" class="cart-item__qty" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                            <div class="quantity">
                                <button type="button" class="quantity__btn quantity__btn--minus">−</button>
                                <input type="number" name="quantity" value="<?= $item['quantity'] ?>" 
                                       min="0" max="<?= $item['stock_quantity'] ?>" class="quantity__input">
                                <button type="button" class="quantity__btn quantity__btn--plus">+</button>
                            </div>
                        </form>
                        <div class="cart-item__total"><?= formatPrice($item['subtotal']) ?></div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="cart-item__remove" title="Удалить">✕</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <div style="padding: 16px 20px; display: flex; justify-content: space-between;">
                    <a href="/pages/catalog.php" class="btn btn--outline btn--small">← Продолжить покупки</a>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn--outline btn--small" 
                                data-confirm="Очистить корзину?">Очистить корзину</button>
                    </form>
                </div>
            </div>

            <!-- Итого -->
            <aside class="cart-summary">
                <h3 class="cart-summary__title">Ваш заказ</h3>

                <div class="cart-summary__row">
                    <span>Товаров:</span>
                    <span><?= count($cart['items']) ?> поз.</span>
                </div>
                <div class="cart-summary__row">
                    <span>Сумма:</span>
                    <span><?= formatPrice($cart['total']) ?></span>
                </div>

                <?php if ($cart['promocode']): ?>
                    <div class="cart-summary__row" style="color: var(--color-success);">
                        <span>Промокод <?= e($cart['promocode']['code']) ?>:</span>
                        <span>−<?= formatPrice($cart['discount']) ?></span>
                    </div>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="remove_promo">
                        <button type="submit" class="btn btn--outline btn--small" style="width: 100%; margin-top: 8px;">
                            Убрать промокод
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="promocode-form">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="apply_promo">
                        <input type="text" name="promocode" placeholder="Промокод" required>
                        <button type="submit" class="btn btn--secondary btn--small">OK</button>
                    </form>
                    <div class="text-muted" style="font-size: 12px;">
                        Попробуйте: WELCOME10, SPRING20
                    </div>
                <?php endif; ?>

                <div class="cart-summary__total">
                    <div class="cart-summary__row" style="border: none; padding: 0;">
                        <span>Итого:</span>
                        <span><?= formatPrice($cart['final']) ?></span>
                    </div>
                </div>

                <a href="/pages/checkout.php" class="btn btn--primary btn--block btn--large">
                    Оформить заказ →
                </a>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
