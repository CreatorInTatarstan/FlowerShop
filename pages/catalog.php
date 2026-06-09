<?php
/**
 * Страница каталога с фильтрами и поиском
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Каталог товаров';

// Параметры фильтрации
$categorySlug = clean($_GET['category'] ?? '');
$search = clean($_GET['search'] ?? '');
$priceMin = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$priceMax = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 0;
$sortBy = clean($_GET['sort'] ?? 'new');

// Категории для меню
$categories = db()->fetchAll("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY id");

// Текущая категория
$currentCategory = null;
$categoryIds = [];
if ($categorySlug) {
    $currentCategory = db()->fetchOne(
        "SELECT * FROM categories WHERE slug = ?",
        [$categorySlug]
    );
    if ($currentCategory) {
        // Включаем все подкатегории
        $categoryIds[] = $currentCategory['id'];
        $subs = db()->fetchAll(
            "SELECT id FROM categories WHERE parent_id = ?",
            [$currentCategory['id']]
        );
        foreach ($subs as $sub) {
            $categoryIds[] = $sub['id'];
        }
        $pageTitle = $currentCategory['name'];
    }
}

// Построение запроса
$where = ['p.is_available = 1'];
$params = [];

if (!empty($categoryIds)) {
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $where[] = "p.category_id IN ($placeholders)";
    $params = array_merge($params, $categoryIds);
}

if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.composition LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($priceMin > 0) {
    $where[] = "p.price >= ?";
    $params[] = $priceMin;
}

if ($priceMax > 0) {
    $where[] = "p.price <= ?";
    $params[] = $priceMax;
}

$whereSQL = implode(' AND ', $where);

// Сортировка
$orderBy = 'p.id DESC';
switch ($sortBy) {
    case 'price_asc': $orderBy = 'p.price ASC'; break;
    case 'price_desc': $orderBy = 'p.price DESC'; break;
    case 'name': $orderBy = 'p.name ASC'; break;
}

// Получение товаров
$products = db()->fetchAll(
    "SELECT p.*, c.name AS category_name 
     FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE $whereSQL
     ORDER BY $orderBy",
    $params
);

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="section__title" style="text-align: left;">
        <?= e($pageTitle) ?>
        <?php if ($search): ?>
            <small style="color: var(--color-text-muted); font-size: 18px; font-weight: normal;"> — поиск: «<?= e($search) ?>»</small>
        <?php endif; ?>
    </h1>

    <div class="catalog-layout">
        <!-- Фильтры -->
        <aside class="filters">
            <h3 class="filters__title">Фильтры</h3>

            <form method="GET">
                <?php if ($search): ?>
                    <input type="hidden" name="search" value="<?= e($search) ?>">
                <?php endif; ?>

                <div class="filters__group">
                    <div class="filters__label">Категории</div>
                    <ul class="filters__list">
                        <li>
                            <a href="/pages/catalog.php" class="<?= !$categorySlug ? 'active' : '' ?>">
                                Все товары
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                            <li>
                                <a href="/pages/catalog.php?category=<?= e($cat['slug']) ?>" 
                                   class="<?= $categorySlug === $cat['slug'] ? 'active' : '' ?>">
                                    <?= e($cat['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if ($categorySlug): ?>
                    <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
                <?php endif; ?>

                <div class="filters__group">
                    <div class="filters__label">Цена, ₽</div>
                    <div class="filters__price">
                        <input type="number" name="price_min" placeholder="от" 
                               value="<?= $priceMin > 0 ? $priceMin : '' ?>" min="0">
                        <span>—</span>
                        <input type="number" name="price_max" placeholder="до" 
                               value="<?= $priceMax > 0 ? $priceMax : '' ?>" min="0">
                    </div>
                </div>

                <div class="filters__group">
                    <div class="filters__label">Повод</div>
                    <ul class="filters__list">
                        <li><a href="/pages/catalog.php?search=день рождения">День рождения</a></li>
                        <li><a href="/pages/catalog.php?category=wedding">Свадьба</a></li>
                        <li><a href="/pages/catalog.php?search=8 марта">8 марта</a></li>
                        <li><a href="/pages/catalog.php?search=романт">Романтика</a></li>
                    </ul>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Применить</button>
                <a href="/pages/catalog.php" class="btn btn--outline btn--block mt-1">Сбросить</a>
            </form>
        </aside>

        <!-- Список товаров -->
        <div>
            <div class="catalog-toolbar">
                <div class="catalog-toolbar__count">
                    Найдено товаров: <strong><?= count($products) ?></strong>
                </div>
                <div>
                    <form method="GET" style="display: inline-flex; gap: 8px; align-items: center;">
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'sort'): ?>
                                <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <label>Сортировка:</label>
                        <select name="sort" class="form__select" onchange="this.form.submit()" style="width: auto;">
                            <option value="new" <?= $sortBy === 'new' ? 'selected' : '' ?>>Сначала новые</option>
                            <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Дешевле</option>
                            <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Дороже</option>
                            <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>По названию</option>
                        </select>
                    </form>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <div class="empty-state__icon">🌸</div>
                    <h3>Товары не найдены</h3>
                    <p>Попробуйте изменить параметры поиска</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
