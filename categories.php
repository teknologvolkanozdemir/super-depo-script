<?php
/**
 * Süper Depo Scripti - Kategori Yönetimi
 */

$page_title = 'Kategoriler';
$active_page = 'categories';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

// Kategori Ekleme / Düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $errors[] = 'CSRF doğrulaması geçersiz.';
    } else {
        $action = $_POST['action'];
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '') ?: null;
        $description = trim($_POST['description'] ?? '');

        if ($action === 'create') {
            if (empty($name)) {
                $errors[] = 'Kategori adı zorunludur.';
            } else {
                $stmt = $db->prepare('INSERT INTO categories (name, code, description) VALUES (?, ?, ?)');
                $stmt->execute([$name, $code, $description]);
                log_activity('category_create', "Kategori eklendi: {$name}");
                set_flash('success', "'{$name}' kategorisi başarıyla eklendi.");
                header('Location: categories.php');
                exit;
            }
        } elseif ($action === 'update') {
            $cat_id = (int)($_POST['category_id'] ?? 0);
            if ($cat_id <= 0 || empty($name)) {
                $errors[] = 'Geçerli bir kategori adı giriniz.';
            } else {
                $stmt = $db->prepare('UPDATE categories SET name = ?, code = ?, description = ? WHERE id = ?');
                $stmt->execute([$name, $code, $description, $cat_id]);
                log_activity('category_update', "Kategori güncellendi: {$name}");
                set_flash('success', "'{$name}' kategorisi güncellendi.");
                header('Location: categories.php');
                exit;
            }
        } elseif ($action === 'delete') {
            require_role(['admin', 'manager']);
            $cat_id = (int)($_POST['category_id'] ?? 0);
            // Ürünleri null'a çek veya koru
            $db->prepare('UPDATE products SET category_id = NULL WHERE category_id = ?')->execute([$cat_id]);
            $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$cat_id]);
            log_activity('category_delete', "Kategori silindi ID: {$cat_id}");
            set_flash('success', 'Kategori silindi.');
            header('Location: categories.php');
            exit;
        }
    }
}

// Kategoriler ve Ürün Sayıları
$categories = $db->query('SELECT c.*, COUNT(p.id) as product_count 
                          FROM categories c 
                          LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 
                          GROUP BY c.id ORDER BY c.name ASC')->fetchAll();

// Düzenleme modu
$edit_cat = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_cat = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Ürün Kategorileri</h1>
        <p class="page-subtitle">Depo ürünlerinizi kategorilere ayırarak düzenleyin</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Form Kartı -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $edit_cat ? 'Kategoriyi Düzenle' : 'Yeni Kategori Ekle' ?></h2>
        </div>
        <form action="categories.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit_cat ? 'update' : 'create' ?>">
            <?php if ($edit_cat): ?>
                <input type="hidden" name="category_id" value="<?= (int)$edit_cat['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name" class="form-label">Kategori Adı <span class="form-required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= e($edit_cat['name'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="code" class="form-label">Kategori Kodu (Örn: KAT-ELK):</label>
                <input type="text" id="code" name="code" class="form-control" 
                       value="<?= e($edit_cat['code'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Açıklama:</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= e($edit_cat['description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">
                    <?= $edit_cat ? 'Güncelle' : 'Kategori Ekle' ?>
                </button>
                <?php if ($edit_cat): ?>
                    <a href="categories.php" class="btn btn-outline">İptal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste Kartı -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">Mevcut Kategoriler (<?= count($categories) ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table" aria-label="Kategori Tablosu">
                <thead>
                    <tr>
                        <th scope="col">Kategori Adı</th>
                        <th scope="col">Kod</th>
                        <th scope="col">Açıklama</th>
                        <th scope="col">Ürün Sayısı</th>
                        <th scope="col" style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td><code><?= e($c['code'] ?: '-') ?></code></td>
                        <td><?= e($c['description'] ?: '-') ?></td>
                        <td><span class="badge badge-info"><?= $c['product_count'] ?> Ürün</span></td>
                        <td style="text-align: right;">
                            <a href="categories.php?edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline">Düzenle</a>
                            <form action="categories.php" method="POST" style="display: inline;" onsubmit="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
