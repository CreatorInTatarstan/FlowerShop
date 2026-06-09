<?php
if (!defined('FLOWER_SHOP')) die('Прямой доступ запрещён');

// Получаем категории для меню
$menuCategories = db()->fetchAll(
    "SELECT * FROM categories WHERE parent_id IS NULL ORDER BY id"
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' . SITE_NAME : SITE_NAME . ' — Доставка цветов и букетов' ?></title>
    <meta name="description" content="<?= isset($pageDescription) ? e($pageDescription) : 'Интернет-магазин цветов с доставкой. Свежие букеты, авторские композиции, розы, тюльпаны.' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400&family=Inter:wght@300;400;500;600;700&family=Caveat:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
</head>
<body>

<!-- Верхняя информационная полоса -->
<div class="topbar">
    <div class="container">
        <div class="topbar__left">
            <span class="topbar__item">📞 <a href="tel:<?= preg_replace('/[^+0-9]/', '', SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a></span>
            <span class="topbar__item">🕐 Ежедневно 8:00 — 22:00</span>
        </div>
        <div class="topbar__right">
            <?php if (isLoggedIn()): ?>
                <a href="/pages/account.php" class="topbar__link">Личный кабинет</a>
                <?php if (isAdmin()): ?>
                    <a href="/admin/" class="topbar__link">Админ-панель</a>
                <?php endif; ?>
                <a href="/logout.php" class="topbar__link">Выход</a>
            <?php else: ?>
                <a href="/login.php" class="topbar__link">Вход</a>
                <a href="/register.php" class="topbar__link">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Шапка -->
<header class="header">
    <div class="container header__inner">
        <a href="/" class="logo">
            <span class="logo__icon">🌸</span>
            <span class="logo__text">Цветочная<br><i>лавка</i></span>
        </a>

        <form action="/pages/catalog.php" method="GET" class="search">
            <input type="text" name="search" class="search__input" placeholder="Поиск букетов, цветов..." value="<?= e($_GET['search'] ?? '') ?>">
            <button type="submit" class="search__btn">🔍</button>
        </form>

        <div class="header__actions">
            <a href="/pages/cart.php" class="cart-link">
                <span class="cart-link__icon">🛒</span>
                <span class="cart-link__text">
                    <span class="cart-link__label">Корзина</span>
                    <span class="cart-link__count"><?= cartCount() ?> шт.</span>
                </span>
            </a>
        </div>
    </div>
</header>

<!-- Навигация -->
<nav class="nav">
    <div class="container">
        <ul class="nav__list">
            <li><a href="/" class="nav__link">Главная</a></li>
            <li><a href="/pages/catalog.php" class="nav__link">Весь каталог</a></li>
            <?php foreach ($menuCategories as $cat): ?>
                <li>
                    <a href="/pages/catalog.php?category=<?= e($cat['slug']) ?>" class="nav__link">
                        <?= e($cat['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li><a href="/pages/about.php" class="nav__link">О нас</a></li>
            <li><a href="/pages/contacts.php" class="nav__link">Контакты</a></li>
        </ul>
    </div>
</nav>

<!-- Flash-сообщения -->
<?php if ($flashSuccess = getFlash('success')): ?>
    <div class="flash flash--success container"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError = getFlash('error')): ?>
    <div class="flash flash--error container"><?= e($flashError) ?></div>
<?php endif; ?>

<main class="main">
