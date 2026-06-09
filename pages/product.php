<?php
/**
 * Карточка товара с отзывами
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect('/pages/catalog.php');
}

// Получение товара
$product = db()->fetchOne(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id = ?",
    [$id]
);

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container"><div class="empty-state"><div class="empty-state__icon">🌸</div><h2>Товар не найден</h2><a href="/pages/catalog.php" class="btn btn--primary">В каталог</a></div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $product['name'];

// Обработка добавления отзыва
$reviewError = '';
$reviewSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'])) {
    if (!isLoggedIn()) {
        $reviewError = 'Войдите, чтобы оставить отзыв';
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $reviewError = 'Ошибка безопасности';
    } else {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = clean($_POST['comment'] ?? '');
        if (mb_strlen($comment) < 5) {
            $reviewError = 'Отзыв должен содержать минимум 5 символов';
        } else {
            db()->query(
                "INSERT INTO reviews (user_id, product_id, rating, comment, is_approved) 
                 VALUES (?, ?, ?, ?, 1)",
                [currentUserId(), $id, $rating, $comment]
            );
            $reviewId = (int)db()->lastInsertId();
            $reviewSuccess = 'Спасибо за отзыв!';

            // Уведомление админам в Telegram
            if (file_exists(__DIR__ . '/../telegram/notifier.php')) {
                require_once __DIR__ . '/../telegram/notifier.php';
                try {
                    tgNotifyNewReview($reviewId);
                } catch (Throwable $e) {
                    logMessage('TG notify error: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }
}

// Получение отзывов
$reviews = db()->fetchAll(
    "SELECT r.*, u.name AS user_name 
     FROM reviews r 
     LEFT JOIN users u ON r.user_id = u.id
     WHERE r.product_id = ? AND r.is_approved = 1
     ORDER BY r.created_at DESC",
    [$id]
);

// Средний рейтинг
$avgRating = 0;
if (!empty($reviews)) {
    $avgRating = round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
}

// Похожие товары
$similar = db()->fetchAll(
    "SELECT * FROM products WHERE category_id = ? AND id != ? AND is_available = 1 LIMIT 4",
    [$product['category_id'], $id]
);

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <!-- Хлебные крошки -->
    <nav style="margin-bottom: 20px; font-size: 14px; color: var(--color-text-muted);">
        <a href="/">Главная</a> /
        <a href="/pages/catalog.php">Каталог</a> /
        <?php if ($product['category_slug']): ?>
            <a href="/pages/catalog.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a> /
        <?php endif; ?>
        <span><?= e($product['name']) ?></span>
    </nav>

    <div class="product-detail">
        <div class="product-detail__image">
            <img src="<?= e(productImage($product['image'])) ?>" alt="<?= e($product['name']) ?>">
        </div>
        <div>
            <h1 class="product-detail__title"><?= e($product['name']) ?></h1>

            <?php if ($avgRating > 0): ?>
                <div style="margin-bottom: 16px;">
                    <span style="color: #ffa726;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?= $i <= $avgRating ? '★' : '☆' ?>
                        <?php endfor; ?>
                    </span>
                    <span style="color: var(--color-text-muted); margin-left: 8px;">
                        <?= $avgRating ?> (<?= count($reviews) ?> <?= count($reviews) === 1 ? 'отзыв' : 'отзывов' ?>)
                    </span>
                </div>
            <?php endif; ?>

            <div class="product-detail__price"><?= formatPrice($product['price']) ?></div>

            <div class="product-detail__meta">
                <?php if ($product['size']): ?>
                    <div class="product-detail__meta-row">
                        <span class="product-detail__meta-label">Размер:</span>
                        <span><?= e($product['size']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($product['composition']): ?>
                    <div class="product-detail__meta-row">
                        <span class="product-detail__meta-label">Состав:</span>
                        <span><?= e($product['composition']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="product-detail__meta-row">
                    <span class="product-detail__meta-label">В наличии:</span>
                    <span>
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span style="color: var(--color-success);">✓ <?= $product['stock_quantity'] ?> шт.</span>
                        <?php else: ?>
                            <span style="color: var(--color-error);">Нет в наличии</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="product-detail__meta-row">
                    <span class="product-detail__meta-label">Категория:</span>
                    <span><a href="/pages/catalog.php?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a></span>
                </div>
            </div>

            <?php if ($product['description']): ?>
                <div class="product-detail__description">
                    <?= nl2br(e($product['description'])) ?>
                </div>
            <?php endif; ?>

            <?php if ($product['stock_quantity'] > 0): ?>
                <form method="POST" action="/pages/cart.php" class="product-detail__actions">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <div class="quantity">
                        <button type="button" class="quantity__btn quantity__btn--minus">−</button>
                        <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" class="quantity__input">
                        <button type="button" class="quantity__btn quantity__btn--plus">+</button>
                    </div>
                    <button type="submit" class="btn btn--primary btn--large">🛒 В корзину</button>
                </form>
            <?php else: ?>
                <button class="btn btn--outline btn--large" disabled>Нет в наличии</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Отзывы -->
    <section class="section reviews">
        <h2 class="section__title">Отзывы (<?= count($reviews) ?>)</h2>

        <?php if ($reviewError): ?>
            <div class="flash flash--error"><?= e($reviewError) ?></div>
        <?php endif; ?>
        <?php if ($reviewSuccess): ?>
            <div class="flash flash--success"><?= e($reviewSuccess) ?></div>
        <?php endif; ?>

        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <p>Пока нет отзывов. Будьте первым!</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review">
                    <div class="review__header">
                        <span class="review__author"><?= e($review['user_name']) ?></span>
                        <span class="review__date"><?= formatDate($review['created_at']) ?></span>
                    </div>
                    <div class="review__rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?= $i <= $review['rating'] ? '★' : '☆' ?>
                        <?php endfor; ?>
                    </div>
                    <div><?= nl2br(e($review['comment'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Форма отзыва -->
        <?php if (isLoggedIn()): ?>
            <form method="POST" class="form mt-3" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="review_action" value="add">
                <h3 class="mb-2">Оставить отзыв</h3>

                <div class="form__group">
                    <label class="form__label">Оценка</label>
                    <select name="rating" class="form__select">
                        <option value="5">★★★★★ — Отлично</option>
                        <option value="4">★★★★☆ — Хорошо</option>
                        <option value="3">★★★☆☆ — Нормально</option>
                        <option value="2">★★☆☆☆ — Плохо</option>
                        <option value="1">★☆☆☆☆ — Ужасно</option>
                    </select>
                </div>

                <div class="form__group">
                    <label class="form__label form__label--required">Ваш отзыв</label>
                    <textarea name="comment" class="form__textarea" required minlength="5"></textarea>
                </div>

                <button type="submit" class="btn btn--primary">Отправить отзыв</button>
            </form>
        <?php else: ?>
            <div class="text-center mt-3">
                <a href="/login.php" class="btn btn--outline">Войдите, чтобы оставить отзыв</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- Похожие товары -->
    <?php if (!empty($similar)): ?>
        <section class="section">
            <h2 class="section__title">Похожие товары</h2>
            <div class="products-grid">
                <?php foreach ($similar as $sim): ?>
                    <article class="product-card">
                        <a href="/pages/product.php?id=<?= $sim['id'] ?>" class="product-card__image">
                            <img src="<?= e(productImage($sim['image'])) ?>" alt="<?= e($sim['name']) ?>" loading="lazy">
                        </a>
                        <div class="product-card__body">
                            <h3 class="product-card__title">
                                <a href="/pages/product.php?id=<?= $sim['id'] ?>"><?= e($sim['name']) ?></a>
                            </h3>
                            <div class="product-card__footer">
                                <div class="product-card__price"><?= formatPrice($sim['price']) ?></div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
