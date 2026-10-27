<?php
/**
 * Süper Depo Scripti - Depo ve Raf Konumları Yönetimi
 */

$page_title = 'Depo & Raf Konumları';
$active_page = 'locations';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();
$errors = [];

// Ekleme / Düzenleme / Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $errors[] = 'CSRF geçersiz.';
    } else {
        $action = $_POST['action'];
        $name = trim($_POST['name'] ?? '');
        $warehouse_name = trim($_POST['warehouse_name'] ?? 'Ana Depo') ?: 'Ana Depo';
        $code = trim($_POST['code'] ?? '') ?: null;
        $description = trim($_POST['description'] ?? '');

        if ($action === 'create') {
            if (empty($name)) {
                $errors[] = 'Konum / Raf adı zorunludur.';
            } else {
                $stmt = $db->prepare('INSERT INTO locations (name, warehouse_name, code, description) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $warehouse_name, $code, $description]);
                log_activity('location_create', "Depo konumu eklendi: {$warehouse_name} - {$name}");
                set_flash('success', "'{$name}' konumu eklendi.");
                header('Location: locations.php');
                exit;
            }
        } elseif ($action === 'update') {
            $loc_id = (int)($_POST['location_id'] ?? 0);
            if ($loc_id <= 0 || empty($name)) {
                $errors[] = 'Konum adı zorunludur.';
            } else {
                $stmt = $db->prepare('UPDATE locations SET name = ?, warehouse_name = ?, code = ?, description = ? WHERE id = ?');
                $stmt->execute([$name, $warehouse_name, $code, $description, $loc_id]);
                log_activity('location_update', "Konum güncellendi: {$name}");
                set_flash('success', "'{$name}' güncellendi.");
                header('Location: locations.php');
                exit;
            }
        } elseif ($action === 'delete') {
            require_role(['admin', 'manager']);
            $loc_id = (int)($_POST['location_id'] ?? 0);
            $db->prepare('UPDATE products SET location_id = NULL WHERE location_id = ?')->execute([$loc_id]);
            $db->prepare('DELETE FROM locations WHERE id = ?')->execute([$loc_id]);
            log_activity('location_delete', "Konum silindi ID: {$loc_id}");
            set_flash('success', 'Konum silindi.');
            header('Location: locations.php');
            exit;
        }
    }
}

$locations = $db->query('SELECT l.*, COUNT(p.id) as product_count, COALESCE(SUM(p.stock_quantity), 0) as total_stock 
                          FROM locations l 
                          LEFT JOIN products p ON l.id = p.location_id AND p.is_active = 1 
                          GROUP BY l.id ORDER BY l.warehouse_name, l.name ASC')->fetchAll();

$edit_loc = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM locations WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_loc = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Depo, Koridor ve Raf Konumları</h1>
        <p class="page-subtitle">Ürünlerin depodaki fiziksel yerleşim planını yönetin</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $edit_loc ? 'Konumu Düzenle' : 'Yeni Raf/Konum Ekle' ?></h2>
        </div>
        <form action="locations.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit_loc ? 'update' : 'create' ?>">
            <?php if ($edit_loc): ?>
                <input type="hidden" name="location_id" value="<?= (int)$edit_loc['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="warehouse_name" class="form-label">Depo Adı / Binası:</label>
                <input type="text" id="warehouse_name" name="warehouse_name" class="form-control" 
                       value="<?= e($edit_loc['warehouse_name'] ?? 'Ana Depo') ?>" placeholder="Örn: Ana Depo, Soğuk Depo...">
            </div>

            <div class="form-group">
                <label for="name" class="form-label">Raf / Bölüm / Göz Adı <span class="form-required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= e($edit_loc['name'] ?? '') ?>" required autofocus placeholder="Örn: A-01-02 (A Koridoru Raf 1 Göz 2)">
            </div>

            <div class="form-group">
                <label for="code" class="form-label">Konum Kodu (Barkod için):</label>
                <input type="text" id="code" name="code" class="form-control" 
                       value="<?= e($edit_loc['code'] ?? '') ?>" placeholder="Örn: LOC-A1-02">
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Açıklama & Not:</label>
                <textarea id="description" name="description" class="form-control" rows="2"><?= e($edit_loc['description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">
                    <?= $edit_loc ? 'Güncelle' : 'Konumu Kaydet' ?>
                </button>
                <?php if ($edit_loc): ?>
                    <a href="locations.php" class="btn btn-outline">İptal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title">Kayıtlı Konumlar (<?= count($locations) ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table" aria-label="Konumlar Listesi">
                <thead>
                    <tr>
                        <th scope="col">Depo</th>
                        <th scope="col">Raf / Konum</th>
                        <th scope="col">Kod</th>
                        <th scope="col">Ürün Sayısı</th>
                        <th scope="col">Toplam Stok</th>
                        <th scope="col" style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $l): ?>
                    <tr>
                        <td><strong><?= e($l['warehouse_name']) ?></strong></td>
                        <td><?= e($l['name']) ?></td>
                        <td><code><?= e($l['code'] ?: '-') ?></code></td>
                        <td><span class="badge badge-info"><?= $l['product_count'] ?> Ürün</span></td>
                        <td><?= format_number($l['total_stock'], 0) ?> Adet</td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="locations.php?edit=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline">Düzenle</a>
                            <form action="locations.php" method="POST" style="display: inline;" onsubmit="return confirm('Bu konumu silmek istediğinizden emin misiniz?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="location_id" value="<?= (int)$l['id'] ?>">
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
