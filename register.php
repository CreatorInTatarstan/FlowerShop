<?php
/**
 * Страница регистрации
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Регистрация';
$errors = [];
$data = ['name' => '', 'email' => '', 'phone' => ''];

if (isLoggedIn()) {
    redirect('/pages/account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $data['name'] = clean($_POST['name'] ?? '');
        $data['email'] = clean($_POST['email'] ?? '');
        $data['phone'] = clean($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Валидация
        if (empty($data['name']) || mb_strlen($data['name']) < 2) {
            $errors[] = 'Введите имя (минимум 2 символа)';
        }
        if (!isValidEmail($data['email'])) {
            $errors[] = 'Введите корректный email';
        }
        if (!empty($data['phone']) && !isValidPhone($data['phone'])) {
            $errors[] = 'Введите корректный телефон';
        }
        if (mb_strlen($password) < 6) {
            $errors[] = 'Пароль должен быть не менее 6 символов';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Пароли не совпадают';
        }

        // Проверка уникальности email
        if (empty($errors)) {
            $exists = db()->fetchValue(
                "SELECT COUNT(*) FROM users WHERE email = ?",
                [$data['email']]
            );
            if ($exists) {
                $errors[] = 'Пользователь с таким email уже существует';
            }
        }

        // Регистрация
        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->query(
                "INSERT INTO users (name, email, phone, password_hash, role) 
                 VALUES (?, ?, ?, ?, 'client')",
                [$data['name'], $data['email'], $data['phone'], $hash]
            );
            $userId = db()->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_role'] = 'client';
            $_SESSION['user_name'] = $data['name'];

            logMessage("Регистрация: {$data['email']}", 'INFO');

            // Уведомление админам в Telegram
            if (file_exists(__DIR__ . '/telegram/notifier.php')) {
                require_once __DIR__ . '/telegram/notifier.php';
                try {
                    tgNotifyUserRegistered((int)$userId);
                } catch (Throwable $e) {
                    logMessage('TG notify error: ' . $e->getMessage(), 'ERROR');
                }
            }

            setFlash('success', 'Регистрация прошла успешно! Добро пожаловать!');
            redirect('/pages/account.php');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="auth-page">
        <h1 class="auth-page__title">Регистрация</h1>

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
                <label class="form__label form__label--required" for="name">Имя</label>
                <input type="text" name="name" id="name" class="form__input" 
                       value="<?= e($data['name']) ?>" required>
            </div>

            <div class="form__group">
                <label class="form__label form__label--required" for="email">Email</label>
                <input type="email" name="email" id="email" class="form__input" 
                       value="<?= e($data['email']) ?>" required>
            </div>

            <div class="form__group">
                <label class="form__label" for="phone">Телефон</label>
                <input type="tel" name="phone" id="phone" class="form__input" 
                       value="<?= e($data['phone']) ?>" placeholder="+7 (___) ___-__-__">
            </div>

            <div class="form__row">
                <div class="form__group">
                    <label class="form__label form__label--required" for="password">Пароль</label>
                    <input type="password" name="password" id="password" class="form__input" 
                           required minlength="6">
                </div>
                <div class="form__group">
                    <label class="form__label form__label--required" for="password_confirm">Повторите</label>
                    <input type="password" name="password_confirm" id="password_confirm" 
                           class="form__input" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Зарегистрироваться</button>
        </form>

        <div class="auth-page__footer">
            Уже есть аккаунт? <a href="/login.php">Войти</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
