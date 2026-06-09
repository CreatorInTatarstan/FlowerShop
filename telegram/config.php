<?php
/**
 * Конфигурация Telegram-бота
 * Можно задать токен здесь, либо через админ-панель (тогда сохраняется в БД)
 */

if (!defined('FLOWER_SHOP')) {
    die('Прямой доступ запрещён');
}

// =====================================================
// ЗАДАЙТЕ ВАШ ТОКЕН ОТ @BotFather ЗДЕСЬ
// (либо оставьте пустым и настройте через админ-панель)
// =====================================================
if (!defined('TG_BOT_TOKEN')) {
    define('TG_BOT_TOKEN', '');  // Например: '7123456789:AAH-..._...'
}

if (!defined('TG_BOT_USERNAME')) {
    define('TG_BOT_USERNAME', '');  // Например: 'FlowerShopBot' (без @)
}

// URL вашего сайта (для webhook)
if (!defined('TG_WEBHOOK_URL')) {
    define('TG_WEBHOOK_URL', SITE_URL . '/telegram/bot.php');
}

// API Telegram
define('TG_API_URL', 'https://api.telegram.org/bot');

/**
 * Получить настройку Telegram (из БД с приоритетом, иначе из констант)
 */
function tgGetSetting(string $key, $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = [];
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM telegram_settings");
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $cache = [];
        }
    }
    // Приоритет: БД -> константа -> default
    if (!empty($cache[$key])) {
        return $cache[$key];
    }
    // Константы для основных ключей
    $constMap = [
        'bot_token' => 'TG_BOT_TOKEN',
        'bot_username' => 'TG_BOT_USERNAME',
        'webhook_url' => 'TG_WEBHOOK_URL'
    ];
    if (isset($constMap[$key]) && defined($constMap[$key]) && constant($constMap[$key]) !== '') {
        return constant($constMap[$key]);
    }
    return $default;
}

/**
 * Сохранить настройку Telegram
 */
function tgSaveSetting(string $key, string $value): void {
    db()->query(
        "INSERT INTO telegram_settings (setting_key, setting_value) VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, $value]
    );
}

/**
 * Получить токен бота
 */
function tgBotToken(): string {
    return tgGetSetting('bot_token');
}

/**
 * Получить username бота (для генерации ссылок)
 */
function tgBotUsername(): string {
    return tgGetSetting('bot_username');
}

/**
 * Проверка, настроен ли бот
 */
function tgIsConfigured(): bool {
    return !empty(tgBotToken());
}
