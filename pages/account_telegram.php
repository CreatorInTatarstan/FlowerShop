<?php
/**
 * Страница привязки Telegram-аккаунта
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../telegram/config.php';

requireLogin('/login.php');

$pageTitle = 'Telegram';
$user = currentUser();

// Действия
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_token') {
        $token = bin2hex(random_bytes(16));
        db()->query("UPDATE users SET tg_link_token = ? WHERE id = ?", [$token, $user['id']]);
        setFlash('success', 'Ссылка для привязки создана');
        redirect('/pages/account_telegram.php');
    }

    if ($action === 'unlink') {
        db()->query(
            "UPDATE users SET telegram_id = NULL, telegram_username = NULL, tg_link_token = NULL WHERE id = ?",
            [$user['id']]
        );
        setFlash('success', 'Telegram отвязан');
        redirect('/pages/account_telegram.php');
    }

    if ($action === 'toggle_notifications') {
        $new = $user['tg_notifications'] ? 0 : 1;
        db()->query("UPDATE users SET tg_notifications = ? WHERE id = ?", [$new, $user['id']]);
        setFlash('success', $new ? 'Уведомления включены' : 'Уведомления отключены');
        redirect('/pages/account_telegram.php');
    }
}

// Перечитать пользователя
$user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [currentUserId()]);
$botUsername = tgBotUsername();

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="section__title" style="text-align: left;">Личный кабинет</h1>

    <div class="account-layout">
        <aside class="account-menu">
            <a href="/pages/account.php?tab=orders" class="account-menu__item">📦 Мои заказы</a>
            <a href="/pages/account.php?tab=profile" class="account-menu__item">👤 Профиль</a>
            <a href="/pages/account_telegram.php" class="account-menu__item active">📱 Telegram</a>
            <a href="/logout.php" class="account-menu__item">🚪 Выйти</a>
        </aside>

        <div>
            <h2 class="mb-3">📱 Telegram-уведомления</h2>

            <?php if (!tgIsConfigured() || empty($botUsername)): ?>
                <div class="flash flash--error">
                    ⚠️ Telegram-бот пока не настроен администратором.
                </div>
            <?php elseif ($user['telegram_id']): ?>
                <!-- УЖЕ ПРИВЯЗАН -->
                <div class="tg-banner">
                    <div class="tg-banner__icon">✅</div>
                    <div>
                        <div class="tg-banner__title">Telegram привязан</div>
                        <p>
                            <?php if ($user['telegram_username']): ?>
                                @<?= e($user['telegram_username']) ?>
                            <?php else: ?>
                                ID: <?= e((string)$user['telegram_id']) ?>
                            <?php endif; ?>
                        </p>
                        <a href="https://t.me/<?= e($botUsername) ?>" target="_blank" class="tg-banner__btn">
                            Открыть бот
                        </a>
                    </div>
                </div>

                <div class="form mt-3">
                    <h3 class="mb-2">⚙️ Настройки уведомлений</h3>

                    <p class="mb-2">
                        Статус: <strong><?= $user['tg_notifications'] ? '🔔 Включены' : '🔕 Отключены' ?></strong>
                    </p>
                    <p class="text-muted mb-3">
                        Вы будете получать уведомления о:<br>
                        • Создании заказа<br>
                        • Смене статуса заказа<br>
                        • Действиях с корзиной
                    </p>

                    <form method="POST" style="display: inline-block;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_notifications">
                        <button type="submit" class="btn btn--outline">
                            <?= $user['tg_notifications'] ? '🔕 Отключить' : '🔔 Включить' ?>
                        </button>
                    </form>

                    <form method="POST" style="display: inline-block;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="unlink">
                        <button type="submit" class="btn btn--outline" data-confirm="Отвязать Telegram?">
                            🔓 Отвязать
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- НЕ ПРИВЯЗАН -->
                <div class="tg-banner">
                    <div class="tg-banner__icon">🤖</div>
                    <div>
                        <div class="tg-banner__title">Подключите Telegram-бот</div>
                        <p>Получайте уведомления о заказах, статусах доставки и акциях прямо в Telegram!</p>
                    </div>
                </div>

                <?php if ($user['tg_link_token']):
                    $linkUrl = "https://t.me/{$botUsername}?start={$user['tg_link_token']}";
                ?>
                    <div class="form mt-3">
                        <h3 class="mb-2">🔗 Ваша ссылка для привязки</h3>
                        <p class="text-muted mb-2">Нажмите на кнопку или скопируйте ссылку:</p>

                        <div style="background: var(--color-bg-alt); padding: 16px; border-radius: var(--radius-md); margin-bottom: 16px;">
                            <code style="word-break: break-all; font-size: 13px;"><?= e($linkUrl) ?></code>
                        </div>

                        <a href="<?= e($linkUrl) ?>" target="_blank" class="btn btn--primary btn--large">
                            🚀 Открыть в Telegram
                        </a>

                        <form method="POST" style="display: inline-block; margin-left: 8px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="generate_token">
                            <button type="submit" class="btn btn--outline">🔄 Создать новую ссылку</button>
                        </form>

                        <p class="text-muted mt-2" style="font-size: 13px;">
                            После нажатия откроется бот @<?= e($botUsername) ?>. Нажмите «Запустить» — и аккаунт будет связан.
                        </p>
                    </div>
                <?php else: ?>
                    <form method="POST" class="text-center mt-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="generate_token">
                        <button type="submit" class="btn btn--primary btn--large">
                            🔗 Получить ссылку для привязки
                        </button>
                    </form>
                <?php endif; ?>

                <div class="form mt-3">
                    <h3 class="mb-2">ℹ️ Что вы получите</h3>
                    <ul style="padding-left: 20px; line-height: 2;">
                        <li>📦 Уведомления о статусе ваших заказов</li>
                        <li>✨ Возможность оформлять заказы прямо в чате</li>
                        <li>🛒 Каталог букетов с фото в Telegram</li>
                        <li>📞 Быстрая связь с поддержкой</li>
                        <li>🎁 Эксклюзивные акции и промокоды</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
