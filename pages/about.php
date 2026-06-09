<?php
define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'О компании';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="form" style="max-width: 800px; margin: 0 auto;">
        <h1 class="mb-2">О Цветочной лавке</h1>
        <p class="mb-2">Мы — команда флористов, которые искренне любят своё дело. Уже более 5 лет мы создаём авторские букеты и композиции, которые радуют наших клиентов и их близких.</p>

        <h3 class="mt-3 mb-1">Наши принципы</h3>
        <ul style="padding-left: 20px;">
            <li class="mb-1">🌹 <strong>Свежесть.</strong> Цветы поступают на склад ежедневно от проверенных поставщиков.</li>
            <li class="mb-1">💐 <strong>Авторский подход.</strong> Каждый букет создаётся вручную с вниманием к деталям.</li>
            <li class="mb-1">🚚 <strong>Быстрая доставка.</strong> Минимальное время доставки по Москве — 2 часа.</li>
            <li class="mb-1">💝 <strong>Гарантия.</strong> Если букет не понравится — вернём деньги или заменим.</li>
        </ul>

        <h3 class="mt-3 mb-1">Наша миссия</h3>
        <p>Делать людей счастливее через цветы. Каждый букет — это эмоция, выраженная в форме и цвете. Мы помогаем нашим клиентам выражать свои чувства самым красивым образом.</p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
