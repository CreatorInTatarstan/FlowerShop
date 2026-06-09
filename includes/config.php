<?php
/**
 * Конфигурационный файл проекта
 * Настройки подключения к базе данных и общие параметры сайта
 */

// Запрет прямого доступа к файлу
if (!defined('FLOWER_SHOP')) {
    define('FLOWER_SHOP', true);
}

// =====================================================
// НАСТРОЙКИ БАЗЫ ДАННЫХ (по умолчанию для OSPanel)
// =====================================================
define('DB_HOST', 'MySQL-8.2');
define('DB_NAME', 'flower_shop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// НАСТРОЙКИ САЙТА
// =====================================================
define('SITE_NAME', 'Цветочная лавка');
define('SITE_URL', 'http://flower-shop.local');
define('SITE_EMAIL', 'info@flower-shop.local');
define('SITE_PHONE', '+7 (800) 555-35-35');

// Путь к корню проекта
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', '/uploads');

// =====================================================
// НАСТРОЙКИ СЕССИИ И БЕЗОПАСНОСТИ
// =====================================================
// Время жизни сессии (1 час)
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
ini_set('session.cookie_httponly', 1);

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// ОТЛАДКА (отключить в продакшене)
// =====================================================
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Кодировка
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');

// Часовой пояс
date_default_timezone_set('Europe/Moscow');
