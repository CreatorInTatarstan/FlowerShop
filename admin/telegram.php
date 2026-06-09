<?php
/**
 * Настройки Telegram-бота
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../telegram/config.php';
require_once __DIR__ . '/../telegram/api.php';

$pageTitle = 'Telegram-бот';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        tgSaveSetting('bot_token', clean($_POST['bot_token'] ?? ''));
        tgSaveSetting('bot_username', clean($_POST['bot_username'] ?? ''));
        tgSaveSetting('webhook_url', clean($_POST['webhook_url'] ?? ''));
        tgSaveSetting('admin_chat_ids', clean($_POST['admin_chat_ids'] ?? ''));
        tgSaveSetting('notify_admins_on_order', isset($_POST['notify_admins_on_order']) ? '1' : '0');
        tgSaveSetting('notify_admins_on_review', isset($_POST['notify_admins_on_review']) ? '1' : '0');
        setFlash('success', 'Настройки сохранены');
        redirect('/admin/telegram.php');
    }

    if ($action === 'test') {
        $testChatId = (int)($_POST['test_chat_id'] ?? 0);
        if ($testChatId) {
            $res = tg()->sendMessage($testChatId, "🌸 <b>Тест уведомления</b>\n\nВаш бот настроен и работает! ✅");
            if (!empty($res['ok'])) {
                setFlash('success', 'Тестовое сообщение отправлено');
            } else {
                setFlash('error', 'Ошибка: ' . ($res['description'] ?? 'unknown'));
            }
        }
        redirect('/admin/telegram.php');
    }
}

$logs = [];
try {
    $logs = db()->fetchAll(
        "SELECT * FROM telegram_notifications_log ORDER BY created_at DESC LIMIT 20"
    );
} catch (Exception $e) {
    // Таблица ещё не создана
}

include __DIR__ . '/_header.php';
?>

<div class="stats-grid">
    <div class="stat-card <?= tgIsConfigured() ? 'stat-card--green' : '' ?>">
        <div class="stat-card__label">Статус бота</div>
        <div class="stat-card__value" style="font-size: 18px;">
            <?= tgIsConfigured() ? '✅ Настроен' : '❌ Не настроен' ?>
        </div>
    </div>
    <div class="stat-card stat-card--blue">
        <div class="stat-card__label">Username</div>
        <div class="stat-card__value" style="font-size: 18px;">
            <?= tgBotUsername() ? '@' . e(tgBotUsername()) : '—' ?>
        </div>
    </div>
    <div class="stat-card stat-card--orange">
        <div class="stat-card__label">Привязанных аккаунтов</div>
        <div class="stat-card__value">
            <?= (int)db()->fetchValue("SELECT COUNT(*) FROM users WHERE telegram_id IS NOT NULL") ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Уведомлений сегодня</div>
        <div class="stat-card__value">
            <?= (int)db()->fetchValue("SELECT COUNT(*) FROM telegram_notifications_log WHERE DATE(created_at) = CURDATE()") ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
    <div>
        <form method="POST" class="admin-form mb-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">

            <h3 class="mb-2">⚙️ Настройки бота</h3>

            <div class="form__group">
                <label class="form__label form__label--required">Bot Token (от @BotFather)</label>
                <input type="text" name="bot_token" class="form__input" 
                       value="<?= e(tgGetSetting('bot_token')) ?>" 
                       placeholder="123456789:AAH-...">
                <small class="text-muted">Создайте бота у @BotFather и вставьте сюда токен</small>
            </div>

            <div class="form__group">
                <label class="form__label">Username бота (без @)</label>
                <input type="text" name="bot_username" class="form__input" 
                       value="<?= e(tgGetSetting('bot_username')) ?>" 
                       placeholder="FlowerShopBot">
            </div>

            <div class="form__group">
                <label class="form__label">Webhook URL</label>
                <input type="text" name="webhook_url" class="form__input" 
                       value="<?= e(tgGetSetting('webhook_url')) ?>" 
                       placeholder="<?= TG_WEBHOOK_URL ?>">
                <small class="text-muted">Должен быть HTTPS. Для локальной разработки используйте ngrok</small>
            </div>

            <div class="form__group">
                <label class="form__label">Chat ID администраторов (через запятую)</label>
                <input type="text" name="admin_chat_ids" class="form__input" 
                       value="<?= e(tgGetSetting('admin_chat_ids')) ?>" 
                       placeholder="123456789,987654321">
                <small class="text-muted">Куда отправлять уведомления о новых заказах. Узнать свой ID — у бота <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a></small>
            </div>

            <div class="form__group">
                <label>
                    <input type="checkbox" name="notify_admins_on_order" value="1" 
                           <?= tgGetSetting('notify_admins_on_order') !== '0' ? 'checked' : '' ?>>
                    🆕 Уведомлять админов о новых заказах
                </label>
            </div>

            <div class="form__group">
                <label>
                    <input type="checkbox" name="notify_admins_on_review" value="1" 
                           <?= tgGetSetting('notify_admins_on_review') !== '0' ? 'checked' : '' ?>>
                    ⭐ Уведомлять админов о новых отзывах
                </label>
            </div>

            <button type="submit" class="btn btn--primary">💾 Сохранить настройки</button>
            <a href="/telegram/setup.php" class="btn btn--secondary">📡 Управление webhook</a>
        </form>

        <!-- Тестовая отправка -->
        <?php if (tgIsConfigured()): ?>
            <form method="POST" class="admin-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="test">

                <h3 class="mb-2">🧪 Тестовая отправка</h3>
                <div class="form__group">
                    <label class="form__label">Telegram chat_id для теста</label>
                    <input type="number" name="test_chat_id" class="form__input" placeholder="123456789">
                </div>
                <button type="submit" class="btn btn--secondary">Отправить тест</button>
            </form>
        <?php endif; ?>
    </div>

    <aside>
        <!-- Лог уведомлений -->
        <div class="admin-form">
            <h3 class="mb-2">📋 Последние уведомления</h3>
            <?php if (empty($logs)): ?>
                <p class="text-muted">Уведомлений пока нет</p>
            <?php else: ?>
                <div style="max-height: 500px; overflow-y: auto;">
                    <?php foreach ($logs as $log): ?>
                        <div style="border-bottom:1px solid #eee; padding:10px 0; font-size:13px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <strong style="color:<?= $log['success'] ? '#4caf50' : '#f44336' ?>">
                                    <?= $log['success'] ? '✓' : '✗' ?> <?= e($log['event_type']) ?>
                                </strong>
                                <small class="text-muted"><?= date('H:i', strtotime($log['created_at'])) ?></small>
                            </div>
                            <div class="text-muted">chat_id: <?= $log['telegram_id'] ?></div>
                            <div style="margin-top:4px; color:#666;"><?= e(mb_substr($log['message'], 0, 80)) ?>...</div>
                            <?php if (!$log['success'] && $log['error']): ?>
                                <div style="color:#f44336; font-size:11px;"><?= e($log['error']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
