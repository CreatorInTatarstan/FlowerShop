<?php
/**
 * Страница входа
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Вход в личный кабинет';
$errors = [];
$email = '';

// Уже авторизован — редирект
if (isLoggedIn()) {
    redirect('/pages/account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $email = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = 'Заполните все поля';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Некорректный email';
        } else {
            $user = db()->fetchOne(
                "SELECT * FROM users WHERE email = ?",
                [$email]
            );
            if ($user && password_verify($password, $user['password_hash'])) {
                // Регенерация ID сессии для безопасности
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                logMessage("Вход пользователя: {$user['email']}", 'INFO');
                setFlash('success', 'Добро пожаловать, ' . $user['name'] . '!');

                // Редирект
                $redirect = $_SESSION['redirect_after_login'] ?? '/pages/account.php';
                unset($_SESSION['redirect_after_login']);

                if (in_array($user['role'], ['admin', 'manager'])) {
                    $redirect = '/admin/';
                }
                redirect($redirect);
            } else {
                $errors[] = 'Неверный email или пароль';
                logMessage("Неуспешный вход: $email", 'WARNING');
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="auth-page">
        <h1 class="auth-page__title">Вход в личный кабинет</h1>

        <?php if (!empty($errors)): ?>
            <div class="flash flash--error">
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form" data-validate>
            <?= csrfField() ?>

            <div class="form__group">
                <label class="form__label form__label--required" for="email">Email</label>
                <input type="email" name="email" id="email" class="form__input" 
                       value="<?= e($email) ?>" required autofocus>
            </div>

            <div class="form__group">
                <label class="form__label form__label--required" for="password">Пароль</label>
                <input type="password" name="password" id="password" class="form__input" required>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Войти</button>
        </form>

        <div class="auth-page__footer">
            Нет аккаунта? <a href="/register.php">Зарегистрироваться</a>
        </div>

        <div class="auth-page__footer text-muted" style="font-size: 13px; margin-top: 30px;">
            <strong>Тестовые данные:</strong><br>
            Админ: admin@flowers.local / password123<br>
            Клиент: ivan@example.com / password123
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
