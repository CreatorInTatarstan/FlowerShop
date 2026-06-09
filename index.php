<?php
/**
 * Главная страница интернет-магазина
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Доставка цветов и букетов в Москве';

// Получение категорий для главной
$categories = db()->fetchAll(
    "SELECT * FROM categories WHERE parent_id IS NULL ORDER BY id LIMIT 6"
);

// Иконки для категорий
$categoryIcons = [
    'bouquets' => '💐',
    'roses' => '🌹',
    'tulips' => '🌷',
    'compositions' => '🌺',
    'wedding' => '👰',
    'birthday' => '🎂'
];

// Популярные товары (8 шт.)
$popularProducts = db()->fetchAll(
    "SELECT p.*, c.name AS category_name 
     FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.is_available = 1 
     ORDER BY p.id DESC 
     LIMIT 8"
);

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <!-- Главный баннер -->
    <section class="hero">
        <div class="hero__content">
            <h1 class="hero__title">Свежие <i>цветы</i> с любовью к&nbsp;деталям</h1>
            <p class="hero__text">Авторские букеты и композиции от наших флористов. Бесплатная доставка по Москве при заказе от 3000 ₽.</p>
            <a href="/pages/catalog.php" class="btn btn--primary btn--large">Перейти в каталог</a>
        </div>
    </section>

    <!-- Категории -->
    <section class="section">
        <h2 class="section__title">Категории</h2>
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="/pages/catalog.php?category=<?= e($cat['slug']) ?>" class="category-card">
                    <div class="category-card__icon"><?= $categoryIcons[$cat['slug']] ?? '🌼' ?></div>
                    <div class="category-card__name"><?= e($cat['name']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Популярные товары -->
    <section class="section">
        <h2 class="section__title">Популярные букеты</h2>
        <div class="products-grid">
            <?php foreach ($popularProducts as $product): ?>
                <article class="product-card">
                    <a href="/pages/product.php?id=<?= $product['id'] ?>" class="product-card__image">
                        <img src="<?= e(productImage($product['image'])) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                    </a>
                    <div class="product-card__body">
                        <h3 class="product-card__title">
                            <a href="/pages/product.php?id=<?= $product['id'] ?>"><?= e($product['name']) ?></a>
                        </h3>
                        <?php if ($product['size']): ?>
                            <div class="product-card__size"><?= e($product['size']) ?></div>
                        <?php endif; ?>
                        <div class="product-card__footer">
                            <div class="product-card__price"><?= formatPrice($product['price']) ?></div>
                            <form method="POST" action="/pages/cart.php" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" class="btn btn--primary btn--small">В корзину</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <a href="/pages/catalog.php" class="btn btn--outline">Смотреть все товары</a>
        </div>
    </section>

    <!-- Преимущества -->
    <section class="section">
        <h2 class="section__title">Почему выбирают нас</h2>
        <div class="categories-grid">
            <div class="category-card">
                <div class="category-card__icon">🚚</div>
                <div class="category-card__name">Быстрая доставка</div>
                <p class="text-muted mt-1">От 2 часов по Москве</p>
            </div>
            <div class="category-card">
                <div class="category-card__icon">🌿</div>
                <div class="category-card__name">Свежие цветы</div>
                <p class="text-muted mt-1">Прямые поставки</p>
            </div>
            <div class="category-card">
                <div class="category-card__icon">💳</div>
                <div class="category-card__name">Удобная оплата</div>
                <p class="text-muted mt-1">Картой, наличными или онлайн</p>
            </div>
            <div class="category-card">
                <div class="category-card__icon">🎁</div>
                <div class="category-card__name">Гарантия качества</div>
                <p class="text-muted mt-1">Возврат, если букет не понравится</p>
            </div>
        </div>
    </section>

    <!-- Цитата -->
    <div class="quote">
        Цветы — это улыбка, которую можно подарить
    </div>

    <!-- Telegram-баннер -->
    <?php
    require_once __DIR__ . '/telegram/config.php';
    $tgBotName = tgBotUsername();
    if ($tgBotName):
    ?>
        <section class="section">
            <div class="tg-banner">
                <div class="tg-banner__icon">📱</div>
                <div style="flex: 1;">
                    <div class="tg-banner__title">Наш Telegram-бот</div>
                    <p style="margin-bottom: 12px;">Заказывайте букеты, отслеживайте статус доставки и получайте уведомления прямо в Telegram!</p>
                    <a href="https://t.me/<?= e($tgBotName) ?>" target="_blank" class="tg-banner__btn">
                        🚀 Открыть @<?= e($tgBotName) ?>
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Как мы работаем -->
    <section class="section">
        <h2 class="section__title">Как мы работаем</h2>
        <div class="categories-grid">
            <div class="category-card">
                <div class="category-card__icon">1️⃣</div>
                <div class="category-card__name">Выбираете букет</div>
                <p class="text-muted mt-1">В каталоге или у флориста</p>
            </div>
            <div class="category-card">
                <div class="category-card__icon">2️⃣</div>
                <div class="category-card__name">Оформляете заказ</div>
                <p class="text-muted mt-1">Удобный способ оплаты</p>
            </div>
            <div class="category-card">
                <div class="category-card__icon">3️⃣</div>
                <div class="category-card__name">Мы собираем</div>
                <p class="text-muted mt-1">Свежие цветы, авторская работа</p>
            </div>
            <div class="category-card">
                <div class="category-card__icon">4️⃣</div>
                <div class="category-card__name">Доставляем</div>
                <p class="text-muted mt-1">Точно в назначенное время</p>
            </div>
        </div>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
