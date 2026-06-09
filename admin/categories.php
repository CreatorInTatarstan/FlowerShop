<?php
/**
 * Управление категориями
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Категории';
$errors = [];

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = clean($_POST['name'] ?? '');
        $slug = clean($_POST['slug'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
        $editId = (int)($_POST['id'] ?? 0);

        if (empty($name)) $errors[] = 'Введите название';
        if (empty($slug)) $slug = makeSlug($name);

        if (empty($errors)) {
            if ($action === 'add') {
                db()->query(
                    "INSERT INTO categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)",
                    [$name, $slug, $description, $parentId]
                );
                setFlash('success', 'Категория добавлена');
            } else {
                db()->query(
                    "UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?",
                    [$name, $slug, $description, $parentId, $editId]
                );
                setFlash('success', 'Категория обновлена');
            }
            redirect('/admin/categories.php');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $hasProducts = db()->fetchValue("SELECT COUNT(*) FROM products WHERE category_id = ?", [$id]);
        if ($hasProducts) {
            setFlash('error', 'Нельзя удалить: в категории есть товары');
        } else {
            db()->query("DELETE FROM categories WHERE id = ?", [$id]);
            setFlash('success', 'Категория удалена');
        }
        redirect('/admin/categories.php');
    }
}

$categories = db()->fetchAll(
    "SELECT c.*, p.name AS parent_name, 
            (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS products_count
     FROM categories c 
     LEFT JOIN categories p ON c.parent_id = p.id 
     ORDER BY c.parent_id IS NULL DESC, c.id"
);

$editId = (int)($_GET['edit'] ?? 0);
$editCategory = $editId ? db()->fetchOne("SELECT * FROM categories WHERE id = ?", [$editId]) : null;

include __DIR__ . '/_header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="flash flash--error">
        <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <div>
        <h3 class="mb-2">Список категорий</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Slug</th>
                    <th>Родитель</th>
                    <th>Товаров</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= $cat['id'] ?></td>
                    <td><strong><?= e($cat['name']) ?></strong></td>
                    <td><?= e($cat['slug']) ?></td>
                    <td><?= e($cat['parent_name'] ?? '—') ?></td>
                    <td><?= $cat['products_count'] ?></td>
                    <td class="admin-table__actions">
                        <a href="?edit=<?= $cat['id'] ?>" class="btn btn--outline btn--small">✏️</a>
                        <form method="POST" style="display: inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn btn--outline btn--small" data-confirm="Удалить категорию?">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div>
        <h3 class="mb-2"><?= $editCategory ? 'Редактирование' : 'Добавить категорию' ?></h3>
        <form method="POST" class="admin-form" data-validate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'add' ?>">
            <?php if ($editCategory): ?>
                <input type="hidden" name="id" value="<?= $editCategory['id'] ?>">
            <?php endif; ?>

            <div class="form__group">
                <label class="form__label form__label--required">Название</label>
                <input type="text" name="name" class="form__input" 
                       value="<?= e($editCategory['name'] ?? '') ?>" required>
            </div>

            <div class="form__group">
                <label class="form__label">Slug (URL)</label>
                <input type="text" name="slug" class="form__input" 
                       value="<?= e($editCategory['slug'] ?? '') ?>" 
                       placeholder="auto">
                <small class="text-muted">Если не заполнено — генерируется автоматически</small>
            </div>

            <div class="form__group">
                <label class="form__label">Родительская категория</label>
                <select name="parent_id" class="form__select">
                    <option value="">— нет (главная категория) —</option>
                    <?php foreach ($categories as $cat): if ($editCategory && $cat['id'] === $editCategory['id']) continue; ?>
                        <option value="<?= $cat['id'] ?>" <?= ($editCategory['parent_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form__group">
                <label class="form__label">Описание</label>
                <textarea name="description" class="form__textarea"><?= e($editCategory['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn--primary"><?= $editCategory ? 'Сохранить' : 'Добавить' ?></button>
            <?php if ($editCategory): ?>
                <a href="categories.php" class="btn btn--outline">Отмена</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
