<?php
define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Доставка и оплата';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="form" style="max-width: 800px; margin: 0 auto;">
        <h1 class="mb-2">Доставка и оплата</h1>

        <h3 class="mt-2 mb-1">🚚 Доставка</h3>
        <p>Доставляем по всей Москве с 8:00 до 22:00 ежедневно.</p>
        <ul style="padding-left: 20px;">
            <li>В пределах МКАД — 500 ₽ (бесплатно при заказе от 3000 ₽)</li>
            <li>За МКАД — расчёт индивидуально по тарифу 30 ₽/км</li>
            <li>Срочная доставка (от 2 часов) — +500 ₽</li>
        </ul>

        <h3 class="mt-3 mb-1">💳 Оплата</h3>
        <p>Принимаем различные способы оплаты:</p>
        <ul style="padding-left: 20px;">
            <li><strong>Картой при получении</strong> — у курьера есть терминал</li>
            <li><strong>Наличными при получении</strong> — оплата напрямую курьеру</li>
            <li><strong>Онлайн-оплата</strong> — на сайте при оформлении заказа</li>
        </ul>

        <h3 class="mt-3 mb-1">📞 Контакты для срочных вопросов</h3>
        <p>Телефон: <a href="tel:<?= preg_replace('/[^+0-9]/', '', SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a></p>
        <p>Email: <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
