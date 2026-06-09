<?php
define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Контакты';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="form" style="max-width: 800px; margin: 0 auto;">
        <h1 class="mb-3">Контакты</h1>

        <div class="form__row">
            <div>
                <h3 class="mb-1">📍 Адрес</h3>
                <p>г. Москва, ул. Цветочная, д. 1</p>
                <p>Метро: Цветной бульвар (5 минут пешком)</p>
            </div>
            <div>
                <h3 class="mb-1">🕐 Режим работы</h3>
                <p>Пн-Вс: 8:00 — 22:00</p>
                <p>Без выходных</p>
            </div>
        </div>

        <div class="form__row mt-2">
            <div>
                <h3 class="mb-1">📞 Телефон</h3>
                <p><a href="tel:<?= preg_replace('/[^+0-9]/', '', SITE_PHONE) ?>" style="font-size: 18px;"><?= e(SITE_PHONE) ?></a></p>
                <p class="text-muted">Звонки принимаем круглосуточно</p>
            </div>
            <div>
                <h3 class="mb-1">✉️ Email</h3>
                <p><a href="mailto:<?= e(SITE_EMAIL) ?>" style="font-size: 18px;"><?= e(SITE_EMAIL) ?></a></p>
                <p class="text-muted">Отвечаем в течение 30 минут</p>
            </div>
        </div>

        <h3 class="mt-3 mb-1">🌐 Социальные сети</h3>
        <p>Подписывайтесь, чтобы первыми узнавать о новых букетах и акциях:</p>
        <p>
            <a href="#" class="btn btn--outline btn--small">VK</a>
            <a href="#" class="btn btn--outline btn--small">Telegram</a>
            <a href="#" class="btn btn--outline btn--small">Instagram</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
