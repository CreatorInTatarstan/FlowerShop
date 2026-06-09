<?php
/**
 * Управление пользователями
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Пользователи';

// Изменение роли
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_role') {
        $id = (int)($_POST['user_id'] ?? 0);
        $role = clean($_POST['role'] ?? 'client');
        if ($id !== currentUserId() && in_array($role, ['admin', 'manager', 'client'])) {
            db()->query("UPDATE users SET role = ? WHERE id = ?", [$role, $id]);
            setFlash('success', 'Роль изменена');
        }
        redirect('/admin/users.php');
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id !== currentUserId()) {
            db()->query("DELETE FROM users WHERE id = ?", [$id]);
            setFlash('success', 'Пользователь удалён');
        } else {
            setFlash('error', 'Нельзя удалить себя');
        }
        redirect('/admin/users.php');
    }
}

// Фильтр
$role = clean($_GET['role'] ?? '');
$search = clean($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}
if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$users = db()->fetchAll(
    "SELECT u.*, 
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS orders_count,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id AND status != 'cancelled') AS total_spent
     FROM users u 
     WHERE " . implode(' AND ', $where) . "
     ORDER BY u.created_at DESC",
    $params
);

include __DIR__ . '/_header.php';
?>

<form method="GET" class="admin-filters">
    <div class="form__group">
        <label class="form__label">Поиск</label>
        <input type="text" name="search" class="form__input" value="<?= e($search) ?>" placeholder="Имя, email или телефон">
    </div>
    <div class="form__group">
        <label class="form__label">Роль</label>
        <select name="role" class="form__select">
            <option value="">Все</option>
            <option value="client" <?= $role === 'client' ? 'selected' : '' ?>>Клиенты</option>
            <option value="manager" <?= $role === 'manager' ? 'selected' : '' ?>>Менеджеры</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Администраторы</option>
        </select>
    </div>
    <button type="submit" class="btn btn--primary">Фильтр</button>
    <a href="?" class="btn btn--outline">Сброс</a>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Имя</th>
            <th>Email</th>
            <th>Телефон</th>
            <th>Роль</th>
            <th>Заказов</th>
            <th>Сумма</th>
            <th>Регистрация</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><strong><?= e($u['name']) ?></strong></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['phone']) ?: '—' ?></td>
            <td>
                <?php if ($u['id'] === currentUserId()): ?>
                    <span class="status-badge status-completed"><?= e($u['role']) ?></span>
                <?php else: ?>
                    <form method="POST" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <select name="role" class="form__select" onchange="this.form.submit()" style="padding: 4px 8px;">
                            <option value="client" <?= $u['role'] === 'client' ? 'selected' : '' ?>>client</option>
                            <option value="manager" <?= $u['role'] === 'manager' ? 'selected' : '' ?>>manager</option>
                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                        </select>
                    </form>
                <?php endif; ?>
            </td>
            <td><?= $u['orders_count'] ?></td>
            <td><?= formatPrice($u['total_spent']) ?></td>
            <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td class="admin-table__actions">
                <?php if ($u['id'] !== currentUserId()): ?>
                    <form method="POST" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn--outline btn--small" data-confirm="Удалить пользователя?">🗑️</button>
                    </form>
                <?php else: ?>
                    <span class="text-muted">— Это вы —</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/_footer.php'; ?>
