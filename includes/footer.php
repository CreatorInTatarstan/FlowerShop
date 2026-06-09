<?php if (!defined('FLOWER_SHOP')) die('Прямой доступ запрещён'); ?>
</main>

<footer class="footer">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__col">
                <h3 class="footer__title">Цветочная лавка</h3>
                <p>Свежие цветы и авторские букеты с доставкой на дом. Работаем ежедневно с 8:00 до 22:00.</p>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Каталог</h4>
                <ul class="footer__list">
                    <li><a href="/pages/catalog.php?category=bouquets">Букеты</a></li>
                    <li><a href="/pages/catalog.php?category=roses">Розы</a></li>
                    <li><a href="/pages/catalog.php?category=tulips">Тюльпаны</a></li>
                    <li><a href="/pages/catalog.php?category=compositions">Композиции</a></li>
                </ul>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Информация</h4>
                <ul class="footer__list">
                    <li><a href="/pages/about.php">О компании</a></li>
                    <li><a href="/pages/delivery.php">Доставка и оплата</a></li>
                    <li><a href="/pages/contacts.php">Контакты</a></li>
                </ul>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Контакты</h4>
                <p>📞 <a href="tel:<?= preg_replace('/[^+0-9]/', '', SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a></p>
                <p>✉️ <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></p>
                <p>📍 г. Москва, ул. Цветочная, 1</p>
            </div>
        </div>
        <div class="footer__bottom">
            <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Все права защищены.</p>
        </div>
    </div>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
