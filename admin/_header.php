<?php
if (!defined('FLOWER_SHOP')) die('Прямой доступ запрещён');
requireAdmin();

$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Админ-панель</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <!-- Боковое меню -->
    <aside class="admin-sidebar">
        <div class="admin-sidebar__logo">
            <a href="/admin/">
                🌸 <span>Админ-панель</span>
            </a>
        </div>

        <ul class="admin-menu">
            <li>
                <a href="/admin/" class="admin-menu__item <?= $currentPage === 'index' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">📊</span>
                    <span>Аналитика</span>
                </a>
            </li>
            <li>
                <a href="/admin/orders.php" class="admin-menu__item <?= $currentPage === 'orders' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">📦</span>
                    <span>Заказы</span>
                </a>
            </li>
            <li>
                <a href="/admin/products.php" class="admin-menu__item <?= in_array($currentPage, ['products', 'product_edit']) ? 'active' : '' ?>">
                    <span class="admin-menu__icon">🌹</span>
                    <span>Товары</span>
                </a>
            </li>
            <li>
                <a href="/admin/categories.php" class="admin-menu__item <?= $currentPage === 'categories' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">📁</span>
                    <span>Категории</span>
                </a>
            </li>
            <li>
                <a href="/admin/users.php" class="admin-menu__item <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">👥</span>
                    <span>Пользователи</span>
                </a>
            </li>
            <li>
                <a href="/admin/suppliers.php" class="admin-menu__item <?= in_array($currentPage, ['suppliers', 'deliveries']) ? 'active' : '' ?>">
                    <span class="admin-menu__icon">🚚</span>
                    <span>Поставщики</span>
                </a>
            </li>
            <li>
                <a href="/admin/deliveries.php" class="admin-menu__item <?= $currentPage === 'deliveries' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">📥</span>
                    <span>Поставки</span>
                </a>
            </li>
            <li>
                <a href="/admin/promocodes.php" class="admin-menu__item <?= $currentPage === 'promocodes' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">🎟️</span>
                    <span>Промокоды</span>
                </a>
            </li>
            <li>
                <a href="/admin/reviews.php" class="admin-menu__item <?= $currentPage === 'reviews' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">⭐</span>
                    <span>Отзывы</span>
                </a>
            </li>
            <li>
                <a href="/admin/telegram.php" class="admin-menu__item <?= $currentPage === 'telegram' ? 'active' : '' ?>">
                    <span class="admin-menu__icon">📱</span>
                    <span>Telegram-бот</span>
                </a>
            </li>
            <li style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
                <a href="/" class="admin-menu__item">
                    <span class="admin-menu__icon">🏠</span>
                    <span>На сайт</span>
                </a>
            </li>
            <li>
                <a href="/logout.php" class="admin-menu__item">
                    <span class="admin-menu__icon">🚪</span>
                    <span>Выход</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-header">
            <h1 class="admin-header__title"><?= isset($pageTitle) ? e($pageTitle) : 'Админ-панель' ?></h1>
            <div class="admin-header__user">
                👤 <?= e($user['name']) ?> (<?= e($user['role']) ?>)
            </div>
        </div>

        <?php if ($flash = getFlash('success')): ?>
            <div class="flash flash--success"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($flash = getFlash('error')): ?>
            <div class="flash flash--error"><?= e($flash) ?></div>
        <?php endif; ?>
