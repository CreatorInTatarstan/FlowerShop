<?php
/**
 * Выход из системы
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Очищаем сессию
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Запускаем новую сессию для flash-сообщения
session_start();
setFlash('success', 'Вы вышли из системы');
redirect('/');
