<?php
/**
 * Установка webhook и команд бота
 * Использование: открыть http://flower-shop.local/telegram/setup.php (только для админа)
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

requireAdmin();

$pageTitle = 'Настройка Telegram-бота';

$result = null;
$action = $_GET['action'] ?? '';

if ($action === 'set_webhook') {
    $url = tgGetSetting('webhook_url') ?: TG_WEBHOOK_URL;
    $result = tg()->setWebhook($url);
}
if ($action === 'delete_webhook') {
    $result = tg()->deleteWebhook();
}
if ($action === 'info') {
    $result = tg()->getWebhookInfo();
}
if ($action === 'get_me') {
    $result = tg()->getMe();
}
if ($action === 'set_commands') {
    $result = tg()->setMyCommands([
        ['command' => 'start', 'description' => 'Запустить бота'],
        ['command' => 'catalog', 'description' => '🛍 Каталог букетов'],
        ['command' => 'cart', 'description' => '🛒 Моя корзина'],
        ['command' => 'orders', 'description' => '📦 Мои заказы'],
        ['command' => 'account', 'description' => '👤 Личный кабинет'],
        ['command' => 'help', 'description' => 'ℹ️ Справка']
    ]);
}

include __DIR__ . '/../admin/_header.php';
?>

<a href="/admin/" class="btn btn--outline mb-3">← В админку</a>

<div class="admin-form mb-3">
    <h3 class="mb-2">📡 Webhook</h3>

    <p><strong>Bot token:</strong> 
        <?= tgBotToken() ? '<span style="color:#4caf50">✅ настроен</span>' : '<span style="color:#f44336">❌ не задан</span>' ?>
    </p>
    <p><strong>Bot username:</strong> <?= tgBotUsername() ? '@' . e(tgBotUsername()) : '<span style="color:#f44336">не задан</span>' ?></p>
    <p><strong>URL webhook:</strong> <code><?= e(tgGetSetting('webhook_url') ?: TG_WEBHOOK_URL) ?></code></p>

    <div class="mt-3" style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="?action=get_me" class="btn btn--outline">🤖 getMe (проверка токена)</a>
        <a href="?action=set_webhook" class="btn btn--primary">📡 Установить webhook</a>
        <a href="?action=info" class="btn btn--outline">ℹ️ Информация о webhook</a>
        <a href="?action=set_commands" class="btn btn--outline">⌨️ Установить команды</a>
        <a href="?action=delete_webhook" class="btn btn--outline" data-confirm="Удалить webhook?">🗑 Удалить webhook</a>
    </div>
</div>

<?php if ($result): ?>
    <div class="admin-form">
        <h3 class="mb-2">Результат</h3>
        <pre style="background:#f8f9fa; padding:16px; border-radius:8px; overflow-x:auto;"><?= e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
<?php endif; ?>

<div class="admin-form mt-3">
    <h3 class="mb-2">📋 Инструкция</h3>
    <ol style="padding-left: 20px; line-height: 2;">
        <li>Создайте бота через <a href="https://t.me/BotFather" target="_blank">@BotFather</a> в Telegram</li>
        <li>Получите <strong>токен</strong> и <strong>username</strong> бота</li>
        <li>Введите их в <a href="/admin/telegram.php">Настройки Telegram</a></li>
        <li>Webhook требует <strong>HTTPS-домен</strong>! Для локальной разработки используйте <a href="https://ngrok.com" target="_blank">ngrok</a> или аналог</li>
        <li>После настройки токена нажмите <strong>«getMe»</strong> для проверки</li>
        <li>Затем <strong>«Установить webhook»</strong> и <strong>«Установить команды»</strong></li>
    </ol>

    <div class="flash flash--info" style="background:#fff3cd; border-color:#ffc107; color:#856404; margin-top:16px;">
        💡 <strong>Локальная разработка с ngrok:</strong><br>
        1. Скачайте <a href="https://ngrok.com/download" target="_blank">ngrok</a><br>
        2. Запустите: <code>ngrok http 80</code><br>
        3. Скопируйте https-URL (например, <code>https://abc123.ngrok-free.app</code>)<br>
        4. В админке → Telegram задайте <strong>Webhook URL</strong> = <code>https://abc123.ngrok-free.app/telegram/bot.php</code>
    </div>
</div>

<?php include __DIR__ . '/../admin/_footer.php'; ?>
