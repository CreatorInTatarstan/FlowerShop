<?php
/**
 * Модерация отзывов
 */

define('FLOWER_SHOP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Отзывы';

// Действия
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'approve') {
        db()->query("UPDATE reviews SET is_approved = 1 WHERE id = ?", [$id]);
        setFlash('success', 'Отзыв одобрен');
    } elseif ($action === 'reject') {
        db()->query("UPDATE reviews SET is_approved = 0 WHERE id = ?", [$id]);
        setFlash('success', 'Отзыв скрыт');
    } elseif ($action === 'delete') {
        db()->query("DELETE FROM reviews WHERE id = ?", [$id]);
        setFlash('success', 'Отзыв удалён');
    }
    redirect('/admin/reviews.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
}

// Фильтр
$filter = clean($_GET['filter'] ?? 'all');

$where = '1=1';
if ($filter === 'pending') {
    $where = "r.is_approved = 0";
} elseif ($filter === 'approved') {
    $where = "r.is_approved = 1";
}

$reviews = db()->fetchAll(
    "SELECT r.*, u.name AS user_name, p.name AS product_name, p.id AS product_id
     FROM reviews r 
     LEFT JOIN users u ON r.user_id = u.id 
     LEFT JOIN products p ON r.product_id = p.id 
     WHERE $where
     ORDER BY r.created_at DESC"
);

// Счётчики
$pendingCount = (int)db()->fetchValue("SELECT COUNT(*) FROM reviews WHERE is_approved = 0");
$approvedCount = (int)db()->fetchValue("SELECT COUNT(*) FROM reviews WHERE is_approved = 1");

include __DIR__ . '/_header.php';
?>

<div class="stats-grid">
    <div class="stat-card stat-card--orange">
        <div class="stat-card__label">Ожидают модерации</div>
        <div class="stat-card__value"><?= $pendingCount ?></div>
    </div>
    <div class="stat-card stat-card--green">
        <div class="stat-card__label">Одобренных</div>
        <div class="stat-card__value"><?= $approvedCount ?></div>
    </div>
    <div class="stat-card stat-card--blue">
        <div class="stat-card__label">Всего отзывов</div>
        <div class="stat-card__value"><?= $pendingCount + $approvedCount ?></div>
    </div>
</div>

<!-- Фильтр -->
<div class="admin-filters">
    <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn--primary' : 'btn--outline' ?>">Все</a>
    <a href="?filter=pending" class="btn <?= $filter === 'pending' ? 'btn--primary' : 'btn--outline' ?>">
        Ожидают (<?= $pendingCount ?>)
    </a>
    <a href="?filter=approved" class="btn <?= $filter === 'approved' ? 'btn--primary' : 'btn--outline' ?>">Одобренные</a>
</div>

<!-- Список отзывов -->
<?php if (empty($reviews)): ?>
    <div class="empty-state">
        <div class="empty-state__icon">⭐</div>
        <h3>Отзывов нет</h3>
    </div>
<?php else: ?>
    <?php foreach ($reviews as $r): ?>
        <div class="admin-form mb-2">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 280px;">
                    <div style="margin-bottom: 8px;">
                        <strong><?= e($r['user_name'] ?? 'Аноним') ?></strong>
                        <span class="text-muted"> — <?= formatDate($r['created_at'], true) ?></span>
                    </div>
                    <div style="margin-bottom: 8px; color: #ffa726;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?= $i <= $r['rating'] ? '★' : '☆' ?>
                        <?php endfor; ?>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <strong>Товар:</strong> 
                        <a href="/pages/product.php?id=<?= $r['product_id'] ?>" target="_blank"><?= e($r['product_name']) ?></a>
                    </div>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 3px solid #d8527c;">
                        <?= nl2br(e($r['comment'])) ?>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php if ($r['is_approved']): ?>
                        <span class="status-badge status-completed">✓ Одобрен</span>
                        <form method="POST" style="margin: 0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn--outline btn--small btn--block">Скрыть</button>
                        </form>
                    <?php else: ?>
                        <span class="status-badge status-new">⏳ На проверке</span>
                        <form method="POST" style="margin: 0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn--primary btn--small btn--block">✓ Одобрить</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" style="margin: 0;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn--outline btn--small btn--block" 
                                data-confirm="Удалить отзыв навсегда?">🗑️ Удалить</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
