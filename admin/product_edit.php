<?php
/**
 * Редактирование / добавление товара
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$isNew = !$id;

$product = $isNew ? [
    'id' => 0,
    'category_id' => 0,
    'name' => '',
    'description' => '',
    'price' => 0,
    'stock_quantity' => 0,
    'image' => '',
    'composition' => '',
    'size' => '',
    'is_available' => 1
] : db()->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);

if (!$isNew && !$product) {
    setFlash('error', 'Товар не найден');
    redirect('/admin/products.php');
}

$pageTitle = $isNew ? 'Добавить товар' : 'Редактировать: ' . $product['name'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Ошибка безопасности';
    } else {
        $product['name'] = clean($_POST['name'] ?? '');
        $product['description'] = clean($_POST['description'] ?? '');
        $product['price'] = (float)($_POST['price'] ?? 0);
        $product['stock_quantity'] = (int)($_POST['stock_quantity'] ?? 0);
        $product['category_id'] = (int)($_POST['category_id'] ?? 0);
        $product['composition'] = clean($_POST['composition'] ?? '');
        $product['size'] = clean($_POST['size'] ?? '');
        $product['is_available'] = isset($_POST['is_available']) ? 1 : 0;

        // Валидация
        if (empty($product['name'])) $errors[] = 'Введите название';
        if ($product['price'] <= 0) $errors[] = 'Цена должна быть больше 0';
        if (!$product['category_id']) $errors[] = 'Выберите категорию';
        if ($product['stock_quantity'] < 0) $errors[] = 'Остаток не может быть отрицательным';

        // Загрузка изображения
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Недопустимый формат изображения (jpg, png, webp, gif)';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Размер файла не должен превышать 5 МБ';
            } else {
                $newName = 'product_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $uploadPath = UPLOAD_PATH . '/' . $newName;
                if (!is_dir(UPLOAD_PATH)) {
                    @mkdir(UPLOAD_PATH, 0755, true);
                }
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $product['image'] = $newName;
                } else {
                    $errors[] = 'Ошибка при загрузке файла';
                }
            }
        }

        if (empty($errors)) {
            if ($isNew) {
                db()->query(
                    "INSERT INTO products 
                     (category_id, name, description, price, stock_quantity, image, composition, size, is_available) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $product['category_id'], $product['name'], $product['description'],
                        $product['price'], $product['stock_quantity'], $product['image'],
                        $product['composition'], $product['size'], $product['is_available']
                    ]
                );
                setFlash('success', 'Товар добавлен');
            } else {
                db()->query(
                    "UPDATE products SET 
                     category_id = ?, name = ?, description = ?, price = ?, 
                     stock_quantity = ?, image = ?, composition = ?, size = ?, is_available = ?
                     WHERE id = ?",
                    [
                        $product['category_id'], $product['name'], $product['description'],
                        $product['price'], $product['stock_quantity'], $product['image'],
                        $product['composition'], $product['size'], $product['is_available'],
                        $id
                    ]
                );
                setFlash('success', 'Товар обновлён');
            }
            redirect('/admin/products.php');
        }
    }
}

$categories = db()->fetchAll("SELECT * FROM categories ORDER BY name");

include __DIR__ . '/_header.php';
?>

<a href="products.php" class="btn btn--outline mb-3">← К списку товаров</a>

<?php if (!empty($errors)): ?>
    <div class="flash flash--error">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="admin-form" data-validate>
    <?= csrfField() ?>

    <div class="form__row">
        <div class="form__group">
            <label class="form__label form__label--required">Название</label>
            <input type="text" name="name" class="form__input" value="<?= e($product['name']) ?>" required>
        </div>
        <div class="form__group">
            <label class="form__label form__label--required">Категория</label>
            <select name="category_id" class="form__select" required>
                <option value="">— выберите —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (int)$product['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form__row">
        <div class="form__group">
            <label class="form__label form__label--required">Цена, ₽</label>
            <input type="number" name="price" class="form__input" value="<?= e($product['price']) ?>" min="0" step="0.01" required>
        </div>
        <div class="form__group">
            <label class="form__label form__label--required">Остаток на складе</label>
            <input type="number" name="stock_quantity" class="form__input" value="<?= e($product['stock_quantity']) ?>" min="0" required>
        </div>
    </div>

    <div class="form__group">
        <label class="form__label">Размер</label>
        <input type="text" name="size" class="form__input" value="<?= e($product['size']) ?>" placeholder="Например: Высота 50 см">
    </div>

    <div class="form__group">
        <label class="form__label">Состав букета</label>
        <textarea name="composition" class="form__textarea"><?= e($product['composition']) ?></textarea>
    </div>

    <div class="form__group">
        <label class="form__label">Описание</label>
        <textarea name="description" class="form__textarea" rows="5"><?= e($product['description']) ?></textarea>
    </div>

    <div class="form__group">
        <label class="form__label">Изображение</label>
        <?php if ($product['image']): ?>
            <div class="mb-2">
                <img src="<?= e(productImage($product['image'])) ?>" alt="" style="width: 200px; height: 200px; object-fit: cover; border-radius: 8px;">
            </div>
        <?php endif; ?>
        <input type="file" name="image" class="form__input" accept="image/*">
        <small class="text-muted">Формат: JPG, PNG, WebP. Максимум 5 МБ.</small>
    </div>

    <div class="form__group">
        <label>
            <input type="checkbox" name="is_available" value="1" <?= $product['is_available'] ? 'checked' : '' ?>>
            Доступен к заказу
        </label>
    </div>

    <button type="submit" class="btn btn--primary"><?= $isNew ? 'Добавить товар' : 'Сохранить' ?></button>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
